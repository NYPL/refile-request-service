<?php
namespace NYPL\Services;

use NYPL\Starter\APIException;
use NYPL\Starter\Config;
use NYPL\Starter\APILogger;
use sip2;

class SIP2Client
{

    // Taken from http://multimedia.3m.com/mws/media/355361O/sip2-protocol.pdf
    // For mapping numeric "Circulation Status" codes to their meaning
    const CIRCULATION_STATUS = [
        1 => 'other',
        2 => 'on order',
        3 => 'available',
        4 => 'charged',
        5 => 'charged; not to be recalled until earliest recall date in process',
        6 => 'in progress',
        7 => 'recalled',
        8 => 'waiting on hold shelf',
        9 => 'waiting to be re-shelved',
        10 => 'in transit between library locations',
        11 => 'claimed returned',
        12 => 'lost',
        13 => 'missing'
    ];

    /**
     * @var \sip2
     */
    protected $sip2Client;

    public function __construct()
    {
        $this->initializeSip2Client();
    }

    /**
     * @throws APIException
     * @return \sip2
     */
    public function getSip2Client()
    {
        return $this->sip2Client;
    }

    /**
     * @param \sip2 $sip2Client
     */
    public function setSip2Client(\sip2 $sip2Client)
    {
        $this->sip2Client = $sip2Client;
    }

    /**
     * @throws APIException
     */
    protected function initializeSip2Client()
    {
        $sipClient = new sip2();

        $sipClient->hostname = Config::get('SIP2_HOSTNAME');
        $sipClient->port = Config::get('SIP2_PORT');

        if (!$sipClient->connect()) {
            throw new APIException(
                'SIP2 connection failure. Please check your configuration.',
                [],
                0,
                null,
                500
            );
        }

        $sipClient->UIDalgorithm = Config::get('SIP2_LOGIN', null, true);
        $sipClient->AC = Config::get('SIP2_TERMINAL_PASSWORD', null, true);

        $this->setSip2Client($sipClient);
    }

    /**
     * Perform a SIP2 "Checkin" call for given item
     *
     * Returns an Object with:
     *  - `statusFlag`: Boolean indicating checkin success
     *  - `afMessage`: String 'AF' field ("Screen Message") from SIP2 response
     *  - `sip2Response`: Stringified JSON with complete, raw SIP2 response
     */
    public function checkin ($item) {
        APILogger::addDebug(
            'Sending SIP2 checkin call',
            [
            'barcode'      => $item['barcode'],
            'locationCode' => $item['location']['code']
            ]
          );

        $sip2Client = $this->getSip2Client();

        APILogger::addDebug('Checking in item', [ 'barcode' => $item['barcode'], 'location' => $item['location']['code'] ]);
        $sip2RefileRequest = $sip2Client->msgCheckin(
            $item['barcode'],
            time(),
            $item['location']['code']
        );

        $result = $sip2Client->parseCheckinResponse(
            $sip2Client->get_message($sip2RefileRequest)
          );

        $response = new \stdClass();
        $response->statusFlag = false;
        $response->afMessage = null;

        APILogger::addDebug('Received SIP2 message', $result);

        // A Refile Request is successful when an item is checked in by
        // the AutomatedCirculation System (ACS), i.e. Ok is set to 1 and
        // no alerts are triggered because of active holds on the item, i.e. Alert is set to N
        // Please refer to documentation on SIP2 responses at
        // https://github.com/NYPL/refile-request-service/wiki/SIP2-Responses
        if ($result['fixed']['Alert'] == 'N' && $result['fixed']['Ok'] == '1') {
            $response->statusFlag = true;
        } else {
            // Log a failed SIP2 status change to AVAILABLE without terminating the request prematurely.
            APILogger::addError("Failed to change status to AVAILABLE. (itemBarcode: {$item['barcode']})");
        }
        if (isset($result['variable']['AF'])) {
            $response->afMessage = $result['variable']['AF'];
        }
        $response->sip2Response = json_encode($result);

        return $response;
    }

    /**
     * Perform SIP2 "ItemInformation" call, returning those properties we care about.
     *
     * Returns an Object with:
     *  - `holdQueueLength`: Integer representing number of active item level holds
     *  - `circulationStatus`: String identifying circulation status
     *
     * @param string $barcode - The item barcode, about which to retrieve information
     *
     */
    public function itemInformation ($barcode) {
        APILogger::addDebug(
            'Sending SIP2 item information-call', [ 'barcode'      => $barcode ]
        );

        $sip2Client = $this->getSip2Client();

        // Determine hold queue length via "Item Information" call (SIP2 17)
        // call before calling refile:
        $sip2ItemInfoRequest = $sip2Client->msgItemInformation($barcode);
        $itemInformation = $sip2Client->parseItemInfoResponse(
          $sip2Client->get_message($sip2ItemInfoRequest)
        );

        // Create a plain object to hold return values:
        $response = new \stdClass();

        if ($itemInformation
          && $itemInformation['variable']
          && $itemInformation['variable']['CF']
          && is_array($itemInformation['variable']['CF'])
          && is_numeric($itemInformation['variable']['CF'][0])
        ) {
            $response->holdQueueLength = intval($itemInformation['variable']['CF'][0]);

            APILogger::addDebug(
              "Determined hold queue length for {$barcode} to be $response->holdQueueLength",
              [ 'barcode'      => $barcode ]
            );
        }

        if ($itemInformation
          && $itemInformation['fixed']
          && $itemInformation['fixed']['CirculationStatus']
          &&  array_key_exists($itemInformation['fixed']['CirculationStatus'], self::CIRCULATION_STATUS)
        ) {
            $response->circulationStatus = self::CIRCULATION_STATUS[$itemInformation['fixed']['CirculationStatus']];
        }

        return $response;
    }

}
