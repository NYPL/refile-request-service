<?php
namespace NYPL\Services\Model\SierraApiRequest;

use NYPL\Starter\APILogger;
use NYPL\Starter\Filter;
use NYPL\Starter\APIException;
use NYPL\Starter\Model\ModelTrait\SierraTrait\SierraReadTrait;
use NYPL\Starter\Model\ModelTrait\SierraTrait\SierraDeleteTrait;

class DeleteVirtualRecord
{
  // use SierraDeleteTrait;
  use SierraReadTrait;

  public function __construct ($item_id) {
    $this->item_id = $item_id;

    $this->fetchData();
  }

  private function fetchData() {
    try {
      $this->bib_id = (new BibByItem($this->item_id))->bib_id;

      $this->patron_id = (new PatronsByItemHold($this->item_id))->patron_id;

      if (!is_null($this->patron_id)) {
        $this->hold_id = (new PatronHoldByItem($this->patron_id, $this->item_id))->hold_id;
      }

      // Now we have the 2-3 ids we need to delete:
      $this->deleteHold();
      $this->deleteBib();
      // Deleting bib appears to trigger deletion of item
      // $this->deleteItem();

      return true;
    } catch (APIException $exception) {
      APILogger::addError('Encountered error deleting virtual record: ' . $exception->getMessage());
      return false;
    } catch (Exception $exception) {
      APILogger::addError('Error: ' . $exception->getMessage());
      return false;
    }   
  }

  private function deleteHold () {
    // If no holds found, delete none:
    if (!isset($this->hold_id)) return;

    APILogger::addDebug("Delete hold: $this->hold_id");
    $resp = $this->sendRequest("patrons/holds/$this->hold_id");
  }

  private function deleteBib() {
    APILogger::addDebug("Delete bib: $this->bib_id");
    $resp = $this->sendRequest("bibs/$this->bib_id");
  }

  private function deleteItem() {
    APILogger::addDebug("Delete item: $this->item_id");
    $resp = $this->sendRequest("items/$this->item_id");
  }

  private function getRequestType() {
    return 'DELETE';
  }

  /**
   *  This is implemented only because I have to.
   */
  private function getSierraPath() {
    return null;
  }
}
