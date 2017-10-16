<?php
namespace NYPL\Services\Model\RefileRequest;
use NYPL\Services\Model\RefileRequestModel;
use NYPL\Starter\Model\ModelTrait\TranslateTrait;
/**
 * @SWG\Definition(title="NewRefileRequest", type="object")
 *
 * @package NYPL\Services\Model\RefileRequest
 */
class NewRefileRequest extends RefileRequestModel
{
    use TranslateTrait;
}
