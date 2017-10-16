<?php
namespace NYPL\Services;

use NYPL\Services\Model\RefileRequest\RefileRequest;
use NYPL\Starter\APIException;
use NYPL\Starter\APILogger;
use NYPL\Starter\JobManager;
use NYPL\Starter\CacheModel\BaseJob\Job;
use NYPL\Starter\CacheModel\JobNotice\JobNoticeCreated;
use NYPL\Starter\CacheModel\JobStatus;
use NYPL\Starter\JobClient;
use NYPL\Starter\JobStatus\JobStatusSuccess;
use Ramsey\Uuid\Uuid;

/**
 * Class JobService
 *
 * @package NYPL\Services
 */
class JobService
{
    const JOB_SUCCESS_MESSAGE = 'Job finished successfully for refile request.';
    const JOB_FAILURE_MESSAGE = 'Job finished unsuccessfully for refile request.';

    /**
     * @var string
     */
    public static $jobId;

    /**
     * @var JobClient
     */
    public static $jobClient;

    /**
     * @var JobStatus
     */
    public static $jobStatus;

    /**
     * @var JobStatusSuccess
     */
    public static $jobStatusSuccess;

    /**
     * @var JobNoticeCreated
     */
    public static $jobNotice;

    /**
     * @return string|null
     */
    protected static function getJobId()
    {
        return self::$jobId;
    }

    /**
     * @param string $jobId
     */
    protected static function setJobId($jobId)
    {
        self::$jobId = $jobId;
    }

    /**
     * @return JobClient
     */
    public static function getJobClient()
    {
        return self::$jobClient;
    }

    /**
     * @param JobClient $jobClient
     */
    public static function setJobClient($jobClient)
    {
        self::$jobClient = $jobClient;
    }

    /**
     * @return JobStatus
     */
    public static function getJobStatus()
    {
        return self::$jobStatus;
    }

    /**
     * @param JobStatus $jobStatus
     */
    public static function setJobStatus(JobStatus $jobStatus)
    {
        self::$jobStatus = $jobStatus;
    }

    /**
     * @return JobStatusSuccess
     */
    public static function getJobStatusSuccess()
    {
        return self::$jobStatusSuccess;
    }

    /**
     * @param JobStatusSuccess $jobStatusSuccess
     */
    public static function setJobStatusSuccess(JobStatusSuccess $jobStatusSuccess)
    {
        self::$jobStatusSuccess = $jobStatusSuccess;
    }

    /**
     * @return JobNoticeCreated
     */
    public static function getJobNotice()
    {
        return self::$jobNotice;
    }

    /**
     * @param JobNoticeCreated $jobNotice
     */
    public static function setJobNotice(JobNoticeCreated $jobNotice)
    {
        self::$jobNotice = $jobNotice;
    }

    /**
     * @param bool $useJobManager
     * @throws \NYPL\Starter\APIException
     * @return string
     */
    public static function generateJobId(bool $useJobManager = true): string
    {
        if ($useJobManager) {
            try {
                $jobId = JobManager::createJob();
                self::setJobId($jobId);
                APILogger::addDebug('Job Service ID created.', [self::getJobId()]);
            } catch (\Exception $exception) {
                APILogger::addError('Not able to communicate with the Jobs Service API.');
                throw new APIException(
                    'Jobs Service failed to generate an ID. Service may be misconfigured or unavailable.',
                    [],
                    0,
                    $exception,
                    $exception->getCode()
                );
            }
        }

        if (!self::getJobId()) {
            self::generateRandomId();
            APILogger::addDebug(
                'Job ID returned as a UUID. If the Jobs Service is needed, please check your configuration.',
                [self::getJobId()]
            );
        }

        return self::getJobId();
    }

    /**
     * Provide a UUID in lieu of creating a job service object.
     */
    protected static function generateRandomId()
    {
        self::setJobId(Uuid::uuid4()->toString());
    }

    /**
     * Instantiate requisite job service elements.
     */
    protected static function initializeJobClient()
    {
        try {
            self::setJobClient(new JobClient());
            self::setJobStatus(new JobStatus());
            self::setJobStatusSuccess(new JobStatusSuccess());
            APILogger::addDebug('Job client and status objects initialized.');
        } catch (\Exception $exception) {
            throw new APIException(
                'Jobs Service failed to initialize. Service may be misconfigured or unavailable.',
                [],
                0,
                $exception,
                $exception->getCode()
            );
        }
    }

    /**
     * @param RefileRequest $refileRequest
     * @param string $message
     */
    public static function beginJob(RefileRequest $refileRequest, $message = '')
    {
        self::initializeJobClient();
        self::buildJobNotice((array)$refileRequest, $message);
        self::getJobStatus()->setNotice(self::getJobNotice());

        APILogger::addDebug(
            'Job is being initiated via the Job Service API. (ID: ' . $refileRequest->getId() . ')',
            [self::getJobStatus()]
        );

        self::getJobClient()->startJob(
            new Job(['id' => $refileRequest->getRefileJobId()]),
            self::getJobStatus()
        );
    }

    /**
     * @param RefileRequest $refileRequest
     */
    public static function finishJob(RefileRequest $refileRequest)
    {
        self::initializeJobClient();
        $data = (array)$refileRequest;

        try {
            self::buildJobNotice($data, self::JOB_SUCCESS_MESSAGE . ' (RefileID: ' . $refileRequest->getId() . ')');
            self::getJobStatusSuccess()->setNotice(self::getJobNotice());

            APILogger::addDebug(
                'Success status sent to the Job Service API. (RefileID: ' . $refileRequest->getId() . ')'
            );

            self::getJobClient()->success(
                new Job(['id' => $refileRequest->getRefileJobId()]),
                self::getJobStatusSuccess()
            );
        } catch (\Exception $exception) {
            APILogger::addInfo(
                'Job threw an exception. ' . $exception->getMessage() . '. (RefileID: ' . $refileRequest->getId() . ')'
            );
        }
    }

    /**
     * @param array  $data
     * @param string $notice
     */
    protected static function buildJobNotice(array $data, $notice = '')
    {
        $jobNotice = new JobNoticeCreated();
        $jobNotice->setData([
            'jobId' => $data['refileJobId']
        ]);
        $jobNotice->setText($notice);
        APILogger::addDebug('Job notice created.', $data);

        self::setJobNotice($jobNotice);
    }
}
