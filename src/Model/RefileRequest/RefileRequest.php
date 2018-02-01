<?php
namespace NYPL\Services\Model\RefileRequest;

use NYPL\Starter\APIException;
use NYPL\Starter\APILogger;
use NYPL\Starter\Model\LocalDateTime;
use NYPL\Starter\Model\ModelInterface\ReadInterface;
use NYPL\Starter\Model\ModelTrait\DBCreateTrait;
use NYPL\Starter\Model\ModelTrait\DBReadTrait;
use NYPL\Starter\Model\ModelTrait\DBUpdateTrait;

/**
 * @SWG\Definition(title="RefileRequest", type="object")
 *
 * @package NYPL\Services\Model\RefileRequest
 */
class RefileRequest extends NewRefileRequest implements ReadInterface
{
    use DBCreateTrait, DBReadTrait, DBUpdateTrait;

    const REQUIRED_FIELDS = 'itemBarcode';

    /**
     * @SWG\Property(example="229")
     * @var int
     */
    public $id;

    /**
     * @SWG\Property(example="991873slx938")
     * @var string
     */
    public $jobId;

    /**
     * @SWG\Property(example=false)
     * @var bool
     */
    public $success = false;

    /**
     * @SWG\Property(example="2016-01-07T02:32:51Z", type="string")
     * @var LocalDateTime
     */
    public $createdDate;

    /**
     * @SWG\Property(example="2016-01-07T02:32:51Z", type="string")
     * @var LocalDateTime
     */
    public $updatedDate;

    /**
     * @SWG\Property(example="Item was put on holdshelf."")
     * @var string
     */
    public $afMessage;

    /**
     * @SWG\Property(example="{"fixed":{}, "variable": {}}")
     * @var string
     */
    public $sip2Response;

    public function getSchema()
    {
        return
            [
                "name" => "RefileRequest",
                "type" => "record",
                "fields" => [
                    ["name" => "id", "type" => "int"],
                    ["name" => "jobId", "type" => ["string", "null"]],
                    ["name" => "itemBarcode", "type" => "string"],
                    ["name" => "success", "type" => "boolean"],
                    ["name" => "createdDate", "type" => ["string", "null"]],
                    ["name" => "updatedDate", "type" => ["string", "null"]],
                    ["name" => "afMessage", "type" => ["string", "null"]],
                    ["name" => "sip2Response", "type" => ["string", "null"]],
                ]
            ];
    }

    /**
     * @return string
     */
    public function getSequenceId()
    {
        return 'refile_request_id_seq';
    }

    /**
     * @return array
     */
    public function getIdFields()
    {
        return ['id'];
    }

    /**
     * @param int|string $id
     */
    public function setId($id)
    {
        $this->id = (int) $id;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return boolean
     */
    public function isSuccess()
    {
        return $this->success;
    }

    /**
     * @param boolean $success
     */
    public function setSuccess($success)
    {
        $this->success = (bool) $success;
    }

    /**
     * @return string
     */
    public function getJobId()
    {
        return $this->jobId;
    }

    /**
     * @param string $jobId
     */
    public function setJobId($jobId)
    {
        $this->jobId = $jobId;
    }

    /**
     * @return LocalDateTime
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * @param LocalDateTime $createdDate
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;
    }

    /**
     * @param string $createdDate
     *
     * @return LocalDateTime
     */
    public function translateCreatedDate($createdDate = '')
    {
        return new LocalDateTime(LocalDateTime::FORMAT_DATE_TIME_RFC, $createdDate);
    }

    /**
     * @return LocalDateTime
     */
    public function getUpdatedDate()
    {
        return $this->updatedDate;
    }

    /**
     * @param LocalDateTime $updatedDate
     */
    public function setUpdatedDate($updatedDate)
    {
        $this->updatedDate = $updatedDate;
    }

    /**
     * @param string $updatedDate
     *
     * @return LocalDateTime
     */
    public function translateUpdatedDate($updatedDate = '')
    {
        return new LocalDateTime(LocalDateTime::FORMAT_DATE_TIME_RFC, $updatedDate);
    }

    /**
     * @return string
     */
    public function getAfMessage()
    {
        return $this->afMessage;
    }

    /**
     * @param string $afMessage
     */
    public function setAfMessage($afMessage)
    {
        $this->afMessage = $afMessage;
    }

    /**
     * @return string
     */
    public function getSip2Response()
    {
        return $this->sip2Response;
    }

    /**
     * @param string $sip2Response
     */
    public function setSip2Response($sip2Response)
    {
        $this->sip2Response = $sip2Response;
    }

    /**
     * @throws APIException
     */
    public function validatePostData()
    {
        $requiredFields = explode(',', self::REQUIRED_FIELDS);

        foreach ($requiredFields as $field) {
            if (!isset($this->$field)) {
                APILogger::addError(
                    'RefileRequest object not instantiated. Bad request data sent.',
                    $this->getRawData()
                );
                throw new APIException("Refile request is missing the {$field} element.", null, 0, null, 400);
            }
        }

        APILogger::addDebug('POST request payload validation passed.');
    }
}
