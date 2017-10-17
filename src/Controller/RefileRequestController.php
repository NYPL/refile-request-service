<?php
namespace NYPL\Services\Controller;

use NYPL\Services\ItemClient;
use NYPL\Services\JobService;
use NYPL\Services\Model\RefileRequest\RefileRequest;
use NYPL\Services\Model\Response\RefileRequestResponse;
use NYPL\Services\ServiceController;
use NYPL\Services\SIP2Client;
use NYPL\Starter\APIException;
use NYPL\Starter\APILogger;
use NYPL\Starter\Model\Response\ErrorResponse;
use Slim\Http\Response;

/**
 * Class RefileRequestController
 *
 * @package NYPL\Services\Controller
 */
class RefileRequestController extends ServiceController
{

    /**
     * @SWG\Post(
     *     path="/v0.1/recap/refile-requests",
     *     summary="Process a refile request",
     *     tags={"recap"},
     *     operationId="createRefileRequest",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *         name="NewRefileRequest",
     *         in="body",
     *         description="Request object based on the included data model",
     *         required=true,
     *         @SWG\Schema(ref="#/definitions/NewRefileRequest")
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Successful operation",
     *         @SWG\Schema(ref="#/definitions/RefileRequestResponse")
     *     ),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized"
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Not found",
     *         @SWG\Schema(ref="#/definitions/ErrorResponse")
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="Generic server error",
     *         @SWG\Schema(ref="#/definitions/ErrorResponse")
     *     ),
     *     security={
     *         {
     *             "api_auth": {"openid offline_access api write:hold_request readwrite:hold_request"}
     *         }
     *     }
     * )
     *
     * @throws APIException
     * @return Response
     */
    public function createRefileRequest()
    {
        try {
            $data = $this->getRequest()->getParsedBody();
            $data['jobId'] = JobService::generateJobId($this->isUseJobService());

            $refileRequest = new RefileRequest($data);

            try {
                $refileRequest->validatePostData();
            } catch (APIException $exception) {
                $this->invalidRequestResponse($exception);
            }

            $refileRequest->create();

            APILogger::addNotice('Beginning refile of item barcode ' . $data['itemBarcode']);

            if ($this->isUseJobService()) {
                APILogger::addDebug('Initiating job.', ['jobID' => $refileRequest->getJobId()]);
                JobService::beginJob($refileRequest);
            }

            APILogger::addNotice('Getting item record');
            $itemClient = new ItemClient();

            $response = $itemClient->get('items?barcode=' . $data['itemBarcode']);

            $item = json_decode($response->getBody(), true)['data'][0];

            APILogger::addNotice('Received item record', $item);

            APILogger::addNotice('Sending SIP2 call', [
                'barcode' => $item['barcode'],
                'locationCode' => $item['location']['code']
            ]);

            $sip2Client = new SIP2Client();

            $refileResponse = $sip2Client->getSip2Client()->msgCheckin(
                $item['barcode'],
                time(),
                $item['location']['code']
            );

            $result = $sip2Client->getSip2Client()->parseCheckinResponse(
                $sip2Client->getSip2Client()->get_message($refileResponse)
            );

            APILogger::addNotice('Received SIP2 message', $result);

            $refileRequest->update(
                ['success' => true]
            );

            if ($this->isUseJobService()) {
                APILogger::addDebug('Updating an existing job.', ['jobID' => $refileRequest->getJobId()]);
                JobService::finishJob($refileRequest);
            }

            return $this->getResponse()->withJson(
                new RefileRequestResponse($refileRequest)
            );

        } catch (\Exception $exception) {
            APILogger::addError('Refile SIP2 request failed: ' . $exception->getMessage());
            return $this->getResponse()->withJson(new ErrorResponse(
                500,
                'sip2-checkin-error',
                'SIP2 connection error',
                $exception
            ))->withStatus(500);
        }
    }
}
