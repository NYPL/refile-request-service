<?php
namespace NYPL\Services\Model\SierraApiRequest;

class SierraBaseRequest
{
  /**
   * Simple util to extract the last part of the path from a URI. For Sierra
   * API resource URIs, this is the "id" part.
   *
   * e.g. extractIdFromUri('https://example.com/path1/path2')
   *      => 'path2'
   */
  protected static function extractIdFromUri($uri) {
    $uri_parts = explode('/', $uri);
    return array_pop($uri_parts);
  }
}
