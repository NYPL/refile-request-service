<?php
namespace NYPL\Services\Model\SierraApiRequest;

use NYPL\Starter\APILogger;
use NYPL\Starter\Filter;
use NYPL\Starter\APIException;
use NYPL\Starter\Model\ModelTrait\SierraTrait;

class DeleteVirtualRecord
{
  // mixin methods for performing Sierra API requests
  // See https://github.com/NYPL/php-microservice-starter/blob/master/src/Model/ModelTrait/SierraTrait.php
  use SierraTrait;

  public function __construct ($item_id) {
    $this->item_id = $item_id;

    $this->fetchData();

    $this->deleteRecords();
  }

  /**
   * Fetch bib id and hold id
   */
  private function fetchData() {
    try {
      $this->fetchBibId();

      $this->fetchHoldId();

      return true;
    } catch (APIException $exception) {
      APILogger::addError('Encountered error fetching bib id and hold id for virtual record: ' . $exception->getMessage());
      return false;
    } catch (Exception $exception) {
      APILogger::addError('Error: ' . $exception->getMessage());
      return false;
    }
  }

  /**
   * Delete hold (if one exists), bib, and item
   */
  protected function deleteRecords () {
    try {
      // Now we have the 2-3 ids we need to delete:
      $this->deleteHold();
      // Deleting bib appears to trigger deletion of item
      $this->deleteBib();

      APILogger::addInfo('Deleted ' . ($this->hold_id ? 'hold, bib, and item' : 'bib and item') . ' for virtual record ' . $this->item_id);

      return true;
    } catch (APIException $exception) {
      APILogger::addError('Encountered error deleting virtual record: ' . $exception->getMessage());
      return false;
    } catch (Exception $exception) {
      APILogger::addError('Error: ' . $exception->getMessage());
      return false;
    }   
  }

  /**
   * Fetch the id of the hold for patron and item
   */
  private function fetchHoldId () {
    // Before we can identify hold id, need to identify patron with hold:
    $this->fetchHoldingPatronId();

    // If patron found to have hold, get hold id by inspecting that patron's
    // holds (I'm told by iii support that this is the most direct route):
    if (!is_null($this->patron_id)) {
      $this->hold_id = (new PatronHoldByItem($this->patron_id, $this->item_id))->hold_id;
    }
  }

  /**
   * Fetch patron id holding member item_id
   */
  private function fetchHoldingPatronId () {
    $this->patron_id = (new PatronsByItemHold($this->item_id))->patron_id;
  }

  /**
   * Fetch relevant bib id based on member item_id
   */
  private function fetchBibId () {
    $this->bib_id = (new BibByItem($this->item_id))->bib_id;
  }

  /**
   * Delete hold by member hold_id if present
   */
  private function deleteHold () {
    // If no holds found, delete none:
    if (!$this->hold_id) return;

    APILogger::addDebug("Deleting hold: $this->hold_id");
    $resp = $this->sendRequest("patrons/holds/$this->hold_id");
  }

  /**
   * Delete bib by member bib_id
   */
  private function deleteBib () {
    // Only proceed if we've first established the bib id:
    if (!isset($this->bib_id)) return;

    APILogger::addDebug("Deleting bib: $this->bib_id");
    $resp = $this->sendRequest("bibs/$this->bib_id");
  }

  /**
   * Delete item by member item_id. Should not be necessary if one deletes hold first.
   */
  private function deleteItem () {
    APILogger::addDebug("Delete item: $this->item_id");
    $resp = $this->sendRequest("items/$this->item_id");
  }

  /**
   * This class actually issues two-three separate requests, but all of them
   * are HTTP DELETEs
   */
  protected function getRequestType () {
    return 'DELETE';
  }

  /**
   * All requests are DELETEs. No body.
   */
  protected function getBody () {
    return null;
  }

  /**
   *  This is implemented only because I have to. The abstract form is defined:
   *  https://github.com/NYPL/php-microservice-starter/blob/b5e200385703e6a668896f44e5e4370011f44fe1/src/Model/ModelTrait/SierraTrait.php#L22
   *
   *  This method is not evaluated because we call the lower-level
   *  `sendRequest` method with explicit sierra paths..
   */
  private function getSierraPath () {
    return null;
  }
}
