<?php
namespace NYPL\Test\Model;

use NYPL\Services\Model\SierraApiRequest\PatronHoldByItem;
use NYPL\Starter\APILogger;
use NYPL\Starter\APIException;
use PHPUnit\Framework\TestCase;

class MockPatronHoldByItem extends PatronHoldByItem{
  protected function sendRequest($path = '', $ignoreNoRecord = false, array $headers = []) {
    $parts = $parts = explode('/', parse_url($path)['path']);
    $id = array_pop($parts);
    return file_get_contents(__DIR__ . '/../Stubs/sierra-patrons-' . $this->patron_id . '-holds.json');
  }
}

class PatronHoldByItemTest extends TestCase
{
  public function testHandlesInvalidResponse()
  {
    // Assert that if Sierra API returns invalid response (indicating invalid
    // item id or other issue), an APIException is thrown
    $this->expectException(APIException::class);

    $instance = new MockPatronHoldByItem('invalidpatron', null);
  }

  public function testHandlesPatronWithNonMatchingHolds()
  {
    $this->expectException(APIException::class);

    $instance = new MockPatronHoldByItem('1234', 'nosuchitemid');
  }

  public function testIdentifiesHoldIdByItemId()
  {
    $instance = new MockPatronHoldByItem('1234', '5678');

    self::assertSame($instance->patron_id, '1234');
    self::assertSame($instance->item_id, '5678');
    self::assertSame($instance->hold_id, 'holdid');
  }
}
