<?php
namespace NYPL\Services\Model\SierraApiRequest;

use NYPL\Starter\APILogger;
use NYPL\Starter\APIException;
use NYPL\Starter\Model\ModelTrait\SierraTrait\SierraReadTrait;

class BibByItem extends SierraBaseRequest
{
  use SierraReadTrait;

  public function __construct ($item_id) {
    $this->item_id = $item_id;

    $this->fetchData();
  }

  private function getRequestType() {
    return 'GET';
  }

  /**
   * Fetch bib for item
   */
  private function fetchData() {
    $resp = $this->sendRequest($this->getSierraPath());

    $data = json_decode($resp);

    if (!$data || !isset($data->bibIds) || !$data->bibIds) throw new APIException('Received invalid response fetching item ' . $this->item_id);

    $this->bib_id = current($data->bibIds);
  }

  /**
   * @return string
   */
  public function getSierraPath()
  {
    $query = [
      'fields' => 'bibIds'
    ];

    return "items/$this->item_id?" . http_build_query($query);
  }
}
