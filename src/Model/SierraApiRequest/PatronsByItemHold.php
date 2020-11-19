<?php
namespace NYPL\Services\Model\SierraApiRequest;

use NYPL\Starter\APILogger;
use NYPL\Starter\APIException;
use NYPL\Starter\Model\ModelTrait\SierraTrait\SierraReadTrait;

class PatronsByItemHold extends SierraBaseRequest
{
  use SierraReadTrait;

  const DEFAULT_LIMIT = 20;

  public function __construct ($item_id) {
    $this->item_id = $item_id;
    $this->patron_id = null;

    $this->fetchData();
  }

  /**
   * This is the request body that will be posted to the Sierra API to query
   * patrons having a hold on our item_id
   */
  private function getBody() {
    return json_encode([
      "target" => [
        "record" => [
          "type" => "patron"
        ],
        // The following is the property id for "The record on which the hold was placed."
        // See https://csdirect.iii.com/sierrahelp/Default.htm#sgil/sgil_lists_specify_criteria_spflds_item.html
        "id" => 80802
      ],
      "expr" => [
        "op" => "equals",
        "operands" => [
          "i$this->item_id"
        ]
      ]
    ]);
  }

  private function getRequestType() {
    return 'POST';
  }

  /**
   * Fetch the patron with a hold on our item
   */
  private function fetchData() {
    // Post query:
    $resp = $this->sendRequest($this->getSierraPath());

    $data = json_decode($resp);

    if (!$data || !isset($data->entries) || !is_array($data->entries)) throw new APIException('Received invalid response fetching patrons holding item ' . $this->item_id);
    // If no patrons have hold, return null
    if (count($data->entries) == 0) return;

    // In practice this should not be possible:
    if (count($data->entries) > 1) throw new APIException('Found multiple patrons holding item ' . $this->item_id);

    $entry = current($data->entries);
    APILogger::addDebug('Found patron entry: ', $entry);
    if (!$entry || !$entry->link) throw new APIException('Received invalid entry retrieving patrons for item ' . $this->item_id);

    $this->patron_id = self::extractIdFromUri($entry->link);
    APILogger::addDebug('Found patron with hold on ' . $this->item_id . ': ' . $this->patron_id);
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

    return "patrons/query?" . http_build_query($query);
  }
}
