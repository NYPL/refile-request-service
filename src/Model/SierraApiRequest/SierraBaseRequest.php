<?php
namespace NYPL\Services\Model\SierraApiRequest;

class SierraBaseRequest
{
  protected static function extractIdFromUri($uri) {
    $uri_parts = explode('/', $uri);
    return array_pop($uri_parts);
  }
}
