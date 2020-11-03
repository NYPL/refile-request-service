<?php
namespace NYPL\Services\Model\SierraApiRequest;

use NYPL\Starter\APILogger;
use NYPL\Starter\APIException;
use NYPL\Starter\Model\ModelTrait\SierraTrait\SierraReadTrait;

class PatronHoldByItem extends SierraBaseRequest
{
  use SierraReadTrait;

  const DEFAULT_LIMIT = 20;

  public function __construct ($patron_id, $item_id) {
    $this->patron_id = $patron_id;
    $this->item_id = $item_id;

    $this->fetchData();
  }

  private function getRequestType() {
    return 'GET';
  }

  private function fetchData() {
    $resp = $this->sendRequest($this->getSierraPath());
    APILogger::addDebug('Got patron hold by item data: ', $resp);

    $data = json_decode($resp);
    APILogger::addDebug('Found holds: ', $data);

    if (!$data || !$data->entries || !is_array($data->entries)) throw new APIException('Received invalid response fetching patron holds for patron ' . $this->patron_id);
    APILogger::addDebug('Found holds: ', $data->entries);

    if (count($data->entries) == 0) throw new APIException('Found no holds for patron ' . $this->patron_id);

    $matching = array_filter($data->entries, function ($entry) {
      return self::extractIdFromUri($entry->record) === $this->item_id;
    });

    APILogger::addDebug('Found matching holds: ', $matching);
    if (count($matching) == 0) throw new APIException("Found no holds for patron $this->patron_id matching item $this->item_id");

    $entry = current($matching);
    $this->hold_id = self::extractIdFromUri($entry->id);
  }

  /**
   * @return string
   */
  public function getSierraPath()
  {
    $query = [
      'offset' => 0,
      'limit' => self::DEFAULT_LIMIT
    ];

    return "patrons/$this->patron_id/holds";
  }
}
