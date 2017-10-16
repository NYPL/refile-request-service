<?php
namespace NYPL\Services\Model;
use NYPL\Starter\Model;
/**
 * Class RefileRequest
 *
 * @package \NYPL\Services\Model
 */
class RefileRequestModel extends Model
{
    /**
     * @SWG\Property(example="1234567890")
     * @var string
     */
    public $itemBarcode;

    /**
     * @SWG\Property(example="991873slx938")
     * @var string
     */
    public $jobId;

    /**
     * @return string
     */
    public function getItemBarcode()
    {
        return $this->itemBarcode;
    }

    /**
     * @param string $itemBarcode
     */
    public function setItemBarcode($itemBarcode)
    {
        $this->itemBarcode = $itemBarcode;
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
}
