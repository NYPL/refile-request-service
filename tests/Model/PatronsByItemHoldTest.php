<?php
namespace NYPL\Test\Model;

use NYPL\Services\Model\SierraApiRequest\PatronsByItemHold;
use NYPL\Starter\APILogger;
use NYPL\Starter\APIException;
use PHPUnit\Framework\TestCase;

class MockPatronsByItemHold extends PatronsByItemHold {
  protected function sendRequest($path = '', $ignoreNoRecord = false, array $headers = []) {
    $id = array_pop(explode('/', parse_url($path)['path']));
    return file_get_contents(__DIR__ . '/../Stubs/sierra-patrons-query-by-item-hold-' . $this->item_id . '.json');
  }
}

class PatronsByItemHoldTest extends TestCase
{
  public function testHandlesInvalidResponse()
  {
    // Assert that if Sierra API returns invalid response (indicating invalid
    // item id or other issue), an APIException is thrown
    $this->expectException(APIException::class);

    $instance = new MockPatronsByItemHold('invaliditem');
  }

  public function testHandlesItemWithNoHolds()
  {
    // Assert that the model records the item_id and extracts the patron id
    // (5678) from the fixture response
    $instance = new MockPatronsByItemHold('itemwithnoholds');

    self::assertSame($instance->item_id, 'itemwithnoholds');
    self::assertSame($instance->patron_id, null);
  }

  public function testParsesPatronId()
  {
    // Assert that the model records the item_id and extracts the patron id
    // (5678) from the fixture response
    $instance = new MockPatronsByItemHold('1234');

    self::assertSame($instance->item_id, '1234');
    self::assertSame($instance->patron_id, '5678');
  }
}
