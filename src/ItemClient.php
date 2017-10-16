<?php
namespace NYPL\Services;
use NYPL\Starter\APIClient;

class ItemClient extends APIClient
{
    protected function isRequiresAuth()
    {
        return true;
    }
}
