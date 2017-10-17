<?php
namespace NYPL\Services\Model\Response;

use NYPL\Services\Model\RefileRequest\RefileRequest;
use NYPL\Starter\Model\Response\SuccessResponse;

/**
 * @SWG\Definition(title="RefileRequestResponse", type="object")
 */
class RefileRequestResponse extends SuccessResponse
{
    /**
     * @SWG\Property
     * @var RefileRequest
     */
    public $data;

}
