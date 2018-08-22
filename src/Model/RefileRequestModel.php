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
     * @SWG\Property(example="Item was put on holdshelf.")
     * @var string
     */
    public $afMessage;
    /**
     * @SWG\Property(example="{fixed:{}, variable: {}}")
     * @var string
     */
    public $sip2Response;

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
        return json_decode($this->sip2Response);
    }
    /**
     * @param string $sip2Response
     */
    public function setSip2Response($sip2Response)
    {
        $this->sip2Response = $sip2Response;
    }
}
