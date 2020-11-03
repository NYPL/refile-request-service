<?php
namespace NYPL\Services\Model\SierraApiRequest;

use NYPL\Starter\APILogger;
use NYPL\Starter\APIException;
use NYPL\Starter\Model\ModelTrait\SierraTrait\SierraDeleteTrait;

class DeleteHold extends SierraBaseRequest
{
  use SierraDeleteTrait;

  public function __construct ($hold_id) {
    $this->hold_id = $hold_id;

    $resp = $this->sendRequest($this->getSierraPath());
  }

  private function getRequestType() {
    return 'DELETE';
  }

  /**
   * @return string
   */
  public function getSierraPath()
  {
    return "holds/$this->hold_id";
  }
}
