<?php
namespace NYPL\Services;

use NYPL\Starter\APIException;
use NYPL\Starter\APILogger;
use NYPL\Starter\Config;
use PHPUnit\Framework\Exception;
use sip2;

class SIP2Client
{
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
     * @return \sip2
     */
    protected function initializeSip2Client()
    {
        $sipClient = new sip2();

        $sipClient->hostname = Config::get('SIP2_HOSTNAME');
        $sipClient->port = Config::get('SIP2_PORT');

        if (!$sipClient->connect()) {
            throw new APIException(
                'SIP2 socket connection error. Please check your configuration.',
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
}
