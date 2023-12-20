<?php
namespace NYPL\Services\Controller;

use GuzzleHttp\Exception\RequestException;
use NYPL\Services\ItemClient;
use NYPL\Services\JobService;
use NYPL\Services\Model\SierraApiRequest\DeleteVirtualRecord;
use NYPL\Services\Model\RefileRequest\RefileRequest;
use NYPL\Services\Model\Response\RefileRequestResponse;
use NYPL\Services\ServiceController;
use NYPL\Services\SIP2Client;
use NYPL\Starter\APIException;
use NYPL\Starter\APILogger;
use NYPL\Starter\Config;
use NYPL\Starter\Filter;
use NYPL\Starter\Model\Response\ErrorResponse;
use NYPL\Starter\ModelSet;
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
     *          {
     *             "api_auth": {"openid offline_access api write:hold_request readwrite:hold_request"}
     *          }
     *     }
     * )
     *
     * @throws APIException
     * @return Response
     */
    public function createRefileRequest()
    {
        // TODO: Call the -sync function asynchronously:
        return $this->createRefileRequestSync();
    }

    /**
     * @SWG\Post(
     *     path="/v0.1/recap/refile-requests-sync",
     *     summary="Create a refile request (synchronous)",
     *     tags={"recap"},
     *     operationId="createRefileRequestSync",
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
     *          {
     *             "api_auth": {"openid offline_access api write:hold_request readwrite:hold_request"}
     *          }
     *     }
     * )
     *
     * @throws APIException
     * @return Response
     */
    public function createRefileRequestSync()
    {
        try {
            $data = $this->getRequest()->getParsedBody();
            if (array_key_exists('jobId', $data)) {
                APILogger::addDebug('Honoring existing jobId for refile request: ' . $data['jobId']);
                JobService::setJobId($data['jobId']);
                $data['jobId'] = $data['jobId'];
            } else {
                $data['jobId'] = JobService::generateJobId($this->isUseJobService());
            }

            $refileRequest = new RefileRequest($data);

            try {
                $refileRequest->validatePostData();
            } catch (APIException $exception) {
                return $this->invalidRequestResponse($exception);
            }

            $refileRequest->create();

            APILogger::addInfo('Preparing refile request of item barcode ' . $data['itemBarcode'] . ' (jobId ' . $data['jobId'] . ')');

            $this->sendJobServiceMessages($refileRequest);
            $itemClient = new ItemClient();
            $itemResponse = $itemClient->get('items?barcode=' . $data['itemBarcode'] . '&nyplSource=sierra-nypl');
            $items = json_decode($itemResponse->getBody(), true)['data'];
            APILogger::addDebug("ItemService returned " . count($items) . " items for {$data['itemBarcode']}");
            if (count($items) > 1) {
              APILogger::addError("ItemService returned multiple (" . count($items) . ") items for {$data['itemBarcode']}!");
            }

            // TODO: Need to support multiple potential matches:
            $item = $items[0];

            // Can proceed with calling SIP2 checkin on any item without itemType 50
            // For items with itemType 50, should delete hold (how to look up?), bib, & item
            $is_virtual_record = $item['fixedFields'] && $item['fixedFields']['61'] && $item['fixedFields']['61']['value'] == '50';
            APILogger::addDebug('Determined item ' . ($is_virtual_record ? 'is' : 'is not') . ' a virtual record');

            if ($is_virtual_record) {
              $result = $this->refileVirtualRecord($item);
            } else {
              $result = $this->refilePermanentRecord($item);
            }

            APILogger::addInfo(
              "Marking refile-request for item {$item['barcode']} success=" . ($result->success ? 'true' : 'false'),
              [
                'barcode'       => $item['barcode'],
                'success'       => $result->success,
                'af_message'    => $result->af_message,
                'sip2_response' => $result->sip2_response
              ]
            );

            // Update refile-request record with result
            $refileRequest->addFilter(new Filter('id', $refileRequest->getId()));
            $refileRequest->read();
            $refileRequest->update(
                [
                    'success' => $result->success,
                    'af_message' => $result->af_message,
                    'sip2_response' => $result->sip2_response
                ]
            );

            // Reset the status for the API response for ReCAP.
            $refileRequest->setSuccess(true);

            return $this->getResponse()->withJson(
                new RefileRequestResponse($refileRequest)
            );

        } catch (RequestException $exception) {
            APILogger::addError('Item Client exception: ' . $exception->getMessage());
            return $this->getResponse()->withJson(
                new ErrorResponse(
                    $exception->getCode(),
                    'refile-client-error',
                    $exception->getMessage(),
                    null
                )
            )->withStatus($exception->getCode());
        } catch (\Exception $exception) {
            APILogger::addError('Refile request failed: ' . $exception->getMessage());
            return $this->getResponse()->withJson(
                new ErrorResponse(
                    500,
                    'refile-server-error',
                    $exception->getMessage(),
                    $exception
                )
            )->withStatus(500);
        }
    }

    /**
     *
     * Refile an item record that is part of our permanent collection (not a virtual record)
     */
    function refilePermanentRecord ($item) {
      if(Config::get('SKIP_SIP2', false)) {
          APILogger::addInfo('SKIP_SIP2 is enabled. Skipping SIP2 calls for item ' . $item['barcode']);
          $result = new \stdClass();
          $result->success = true;
          $result->af_message = 'Skipping SIP2';
          $result->sip2_response = 'Skipping SIP2 for barcode ' . $item['barcode'] . ' because SKIP_SIP2 is enabled';
          return $result;
      }

      $sip2Client = new SIP2Client();

      // Perform "ItemInformation" call to check for holds:
      $itemInformation = $sip2Client->itemInformation($item['barcode']);
      APILogger::addDebug('Received item record', $itemInformation);
      // Track status issues for the database for NYPL only.
      $statusFlag = false;
      $afMessage = null;
      $sip2Response = null;

      // If there are active holds, don't call Checkin
      if ($itemInformation->holdQueueLength > 0) {
          // Set custom "afMessage" noting that we're skipping calling msgCheckin.
          $afMessage = "[Skipping SIP2 Checkin because there are active holds ($itemInformation->holdQueueLength). Circ. status is \"$itemInformation->circulationStatus\"]";

      // Otherwise, there appear to be no active holds, so do Checkin to clear status:
      } else {
          $sip2CheckinResult = $sip2Client->checkin($item);

          $statusFlag = $sip2CheckinResult->statusFlag;
          $afMessage = $sip2CheckinResult->afMessage;
          $sip2Response = $sip2CheckinResult->sip2Response;
      }

      $result = new \stdClass();
      $result->success = $statusFlag;
      $result->af_message = $afMessage;
      $result->sip2_response = $sip2Response;

      return $result;
    }

    /**
     *
     * Refile a virtual item record (a temporary bib & item pair created to track lent partner materials)
     */
    function refileVirtualRecord ($item) {
      $result = new \stdClass();
      $result->success = false;
      $result->af_message = null;
      $result->sip2_response = null;

      try {
        // Use Sierra API to remove any holds and then delete item and bib records:
        $delete_request = new DeleteVirtualRecord($item['id']);
        $result->success = true;

      } catch (APIException $exception) {
        APILogger::addError('Error deleting virtual record: ' . $exception->getMessage());
        $result->af_message = $exception->getMessage();
      }

      return $result;
    }

    /**
     * @SWG\Get(
     *     path="/v0.1/recap/refile-requests",
     *     summary="Get Refile Requests",
     *     tags={"recap"},
     *     operationId="getRefileRequests",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *          name="createdDate",
     *          in="query",
     *          description="Specific start date or date range (e.g. [2013-09-03T13:17:45Z,2013-09-03T13:37:45Z])",
     *          required=false,
     *          type="string",
     *          format="string"
     *     ),
     *     @SWG\Parameter(
     *          name="success",
     *          in="query",
     *          description="Success status of a refile request",
     *          required=false,
     *          type="boolean",
     *          @SWG\Items(
     *              enum={"true", "false"},
     *              default=""
     *          ),
     *          collectionFormat="multi"
     *     ),
     *     @SWG\Parameter(
     *          name="offset",
     *          in="query",
     *          description="",
     *          required=false,
     *          type="integer",
     *          format="integer"
     *     ),
     *     @SWG\Parameter(
     *          name="limit",
     *          in="query",
     *          description="",
     *          required=false,
     *          type="integer",
     *          format="integer"
     *     ),
     *     @SWG\Parameter(
     *          name="includeTotalCount",
     *          in="query",
     *          description="Status to include total count",
     *          required=false,
     *          type="boolean",
     *          @SWG\Items(
     *              enum={"true", "false"},
     *              default=""
     *          ),
     *          collectionFormat="multi"
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
     * @return Response
     * @throws \Exception
     * @throws RequestException
     */
    public function getRefileRequests()
    {
        try {
            $createdDateFilter = $this->getRequest()->getQueryParam('createdDate') ?
                new Filter(
                    'createdDate',
                    $this->getRequest()->getQueryParam('createdDate'),
                    false
                ) : null;

            $refileRequestsSet = new ModelSet(new RefileRequest());
            $refileRequestsSet->setOrderBy('createdDate');
            $refileRequestsSet->setOrderDirection('DESC');

            if ($this->getRequest()->getQueryParam('success')) {
                $refileRequestsSet->addFilter(
                    new Filter(
                        'success',
                        $this->getRequest()->getQueryParam('success'),
                        false
                    )
                );
            }

            return $this->getDefaultReadResponse(
                $refileRequestsSet,
                new RefileRequestResponse(),
                $createdDateFilter
            );
        } catch (RequestException $exception) {
            APILogger::addError('Item Client exception: ' . $exception->getMessage());
            return $this->getResponse()->withJson(
                new ErrorResponse(
                    $exception->getCode(),
                    'refile-client-error',
                    $exception->getMessage(),
                    null
                )
            )->withStatus($exception->getCode());
        } catch (\Exception $exception) {
            APILogger::addError('Getting refile request failed: ' . $exception->getMessage());
            return $this->getResponse()->withJson(
                new ErrorResponse(
                    500,
                    'refile-server-error',
                    $exception->getMessage(),
                    $exception
                )
            )->withStatus(500);
        }
    }

    /**
     * @SWG\Get(
     *     path="/v0.1/recap/refile-errors",
     *     summary="Get Refile Errors",
     *     tags={"recap"},
     *     operationId="getRefileErrors",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     *     @SWG\Parameter(
     *          name="createdDate",
     *          in="query",
     *          description="Specific start date or date range (e.g. [2013-09-03T13:17:45Z,2013-09-03T13:37:45Z])",
     *          required=false,
     *          type="string",
     *          format="string"
     *     ),
     *     @SWG\Parameter(
     *          name="offset",
     *          in="query",
     *          description="",
     *          required=false,
     *          type="integer",
     *          format="integer"
     *     ),
     *     @SWG\Parameter(
     *          name="limit",
     *          in="query",
     *          description="",
     *          required=false,
     *          type="integer",
     *          format="integer"
     *     ),
     *     @SWG\Parameter(
     *          name="includeTotalCount",
     *          in="query",
     *          description="Status to include total count",
     *          required=false,
     *          type="boolean",
     *          @SWG\Items(
     *              enum={"true", "false"},
     *              default=""
     *          ),
     *          collectionFormat="multi"
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
     * @return Response
     * @throws \Exception
     * @throws RequestException
     */
    public function getRefileErrors()
    {
        try {
            if ($this->getRequest()->getQueryParam('createdDate')) {
                $createdDateFilter = new Filter(
                    'createdDate',
                    $this->getRequest()->getQueryParam('createdDate')
                );
            } else {
                $createdDateFilter = null;
            }

            $refileRequestsSet = new ModelSet(new RefileRequest());

            $refileRequestsSet->setOrderBy('createdDate');
            $refileRequestsSet->setOrderDirection('DESC');

            // Partner items do not match /^33/
            $partnerItemsFilter = new Filter(
                'itemBarcode',
                '33%',
                false,
                '',
                'NOT LIKE'
            );

            // Establish succeeded request filter
            $refileSucceededFilter = new Filter(
                'success',
                'true'
            );

            // Partner "error" must match all:
            //  - look like a partner item (via barcode)
            //  - have *succeeded*
            //  - match date filters if any
            $partnerItemsError = new Filter\OrFilter(
                [$partnerItemsFilter, $refileSucceededFilter, $createdDateFilter],
                true
            );

            // NYPL items match /^33/
            $NyplItemsFilter = new Filter(
                'itemBarcode',
                '33%',
                false,
                '',
                'LIKE'
            );

            // Establish failed request filter
            $refileFailedFilter = new Filter(
                'success',
                'false'
            );

            // NYPL error must match all:
            //  - look like an NYPL item (via barcode)
            //  - have failed
            //  - match date filters if any
            $NyplItemsError = new Filter\OrFilter(
                [$NyplItemsFilter, $refileFailedFilter, $createdDateFilter],
                true
            );

            // Because each of these is an OrFilter, they'll be joined by an OR:
            $refileRequestsSet->addFilter($partnerItemsError);
            $refileRequestsSet->addFilter($NyplItemsError);

            return $this->getDefaultReadResponse(
                $refileRequestsSet,
                new RefileRequestResponse()
            );
        } catch (RequestException $exception) {
            APILogger::addError('Item Client exception: ' . $exception->getMessage());
            return $this->getResponse()->withJson(
                new ErrorResponse(
                    $exception->getCode(),
                    'refile-client-error',
                    $exception->getMessage(),
                    null
                )
            )->withStatus($exception->getCode());
        } catch (\Exception $exception) {
            APILogger::addError('Getting refile request failed: ' . $exception->getMessage());
            return $this->getResponse()->withJson(
                new ErrorResponse(
                    500,
                    'refile-server-error',
                    $exception->getMessage(),
                    $exception
                )
            )->withStatus(500);
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
