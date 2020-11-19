<?php
namespace NYPL\Test\Model;

use NYPL\Services\Model\SierraApiRequest\DeleteVirtualRecord;
use NYPL\Starter\APILogger;
use NYPL\Starter\APIException;
use PHPUnit\Framework\TestCase;

class MockDeleteVirtualRecord extends DeleteVirtualRecord {
  public function __construct ($item_id, $mock_data) {
    $this->item_id = $item_id;
    $this->bib_id = $mock_data['bib_id'];
    $this->hold_id = $mock_data['hold_id'];

    // Skipping $this->fetchData() because we've mocked it above

    // Maintain an array of requests performed (cheap spying)
    $this->_requests_made = [];
    $this->deleteRecords();
  }

  protected function sendRequest($path = '', $ignoreNoRecord = false, array $headers = []) {
    // Record requests made
    $this->_requests_made[] = [
      'path' => $path,
      'method' => $this->getRequestType()
    ];
  }
}

class DeleteVirtualRecordTest extends TestCase
{
  /**
   * Confirm that if DeleteVirtualRecord establishes a bib id but no hold id,
   * the instance calls DELETE on the bib and makes no other calls
   */
  public function testDeletesBib()
  {
    $instance = new MockDeleteVirtualRecord('itemid123', [ 'bib_id' => 'bibid456', 'hold_id' => null]);
    $this->assertCount(1, $instance->_requests_made);
    $this->assertContains([ 'path' => 'bibs/bibid456', 'method' => 'DELETE' ], $instance->_requests_made);
  }

  /**
   * Confirm that if DeleteVirtualRecord establishes a bib id but no hold id,
   * the instance calls DELETE on the bib and makes no other calls
   */
  public function testDeletesBibAndHold()
  {
    $instance = new MockDeleteVirtualRecord('itemid123', [ 'bib_id' => 'bibid456', 'hold_id' => 'holdid789']);
    $this->assertCount(2, $instance->_requests_made);
    $this->assertContains([ 'path' => 'bibs/bibid456', 'method' => 'DELETE' ], $instance->_requests_made);
    $this->assertContains([ 'path' => 'patrons/holds/holdid789', 'method' => 'DELETE' ], $instance->_requests_made);
  }
}
