<?php
namespace NYPL\Services\Controller;

use GuzzleHttp\Exception\RequestException;
use NYPL\Services\ItemClient;
use NYPL\Services\JobService;
use NYPL\Services\Model\RefileRequest\RefileRequest;
use NYPL\Services\Model\Response\RefileRequestResponse;
use NYPL\Services\ServiceController;
use NYPL\Services\SIP2Client;
use NYPL\Starter\APIException;
use NYPL\Starter\APILogger;
use NYPL\Starter\Filter;
use NYPL\Starter\Model\Response\ErrorResponse;
use NYPL\Starter\ModelSet;
use NYPL\Starter\OrderBy;
use Slim\Http\Request;
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
                return $this->invalidRequestResponse($exception);
            }

            $refileRequest->create();

            APILogger::addDebug('Preparing refile request of item barcode ' . $data['itemBarcode']);

            $this->sendJobServiceMessages($refileRequest);

            APILogger::addDebug('Getting item record');
            $itemClient = new ItemClient();
            $itemResponse = $itemClient->get('items?barcode=' . $data['itemBarcode']);
            $item = json_decode($itemResponse->getBody(), true)['data'][0];

            APILogger::addDebug('Received item record', $item);

            APILogger::addDebug('Sending SIP2 call', [
                'barcode'      => $item['barcode'],
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

            // Track status issues for the database for NYPL only.
            $statusFlag = false;
            $afMessage = null;
            $sip2Response = null;

            APILogger::addDebug('Received SIP2 message', $result);

            // Log a failed SIP2 status change to AVAILABLE without terminating the request prematurely.
            if ($result['fixed']['Alert'] == 'N' && $result['fixed']['Ok'] == '1') {
                $statusFlag = true;
            } else {
                APILogger::addError('Failed to change status to AVAILABLE.' . ' (itemBarcode: ' . $refileRequest->getItemBarcode() . ')');
            }

            $afMessage = $result['variable']['AF'];
            $sip2Response = json_encode($result);

            $refileRequest->addFilter(new Filter('id', $refileRequest->getId()));
            $refileRequest->read();
            $refileRequest->update(
                [
                    'success' => $statusFlag,
                    'af_message' => $afMessage,
                    'sip2_response' => $sip2Response
                ]
            );

            // Reset the status for the API response for ReCAP.
            $refileRequest->setSuccess(true);

            return $this->getResponse()->withJson(
                new RefileRequestResponse($refileRequest)
            );

        } catch (RequestException $exception) {
            APILogger::addError('Item Client exception: ' . $exception->getMessage());
            return $this->getResponse()->withJson(new ErrorResponse(
                $exception->getCode(),
                'refile-client-error',
                $exception->getMessage(),
                null
            ))->withStatus($exception->getCode());
        } catch (\Exception $exception) {
            APILogger::addError('Refile request failed: ' . $exception->getMessage());
            return $this->getResponse()->withJson(new ErrorResponse(
                500,
                'refile-server-error',
                $exception->getMessage(),
                $exception
            ))->withStatus(500);
        }
    }




    /**
     * @param RefileRequest $refileRequest
     */
    public function sendJobServiceMessages(RefileRequest $refileRequest)
    {
        if ($this->isUseJobService()) {
            APILogger::addDebug('Initiating job.', ['jobID' => $refileRequest->getJobId()]);
            JobService::beginJob(
                $refileRequest,
                'Starting refile request job. (RefileID: ' . $refileRequest->getId() . ')'
            );

            APILogger::addDebug('Finishing refile request job.', ['jobID' => $refileRequest->getJobId()]);
            JobService::finishJob($refileRequest);
        }
    }
}
