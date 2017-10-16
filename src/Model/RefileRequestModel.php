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
}
