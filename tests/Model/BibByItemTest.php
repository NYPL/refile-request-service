<?php
namespace NYPL\Test\Model;

use NYPL\Services\Model\SierraApiRequest\BibByItem;
use NYPL\Starter\APILogger;
use NYPL\Starter\APIException;
use PHPUnit\Framework\TestCase;

class MockBibByItem extends BibByItem {
  protected function sendRequest($path = '', $ignoreNoRecord = false, array $headers = []) {
    $parts = $parts = explode('/', parse_url($path)['path']);
    $id = array_pop($parts);
    return file_get_contents(__DIR__ . '/../Stubs/sierra-items-' . $id . '.json');
  }
}

class BibByItemTest extends TestCase
{
  public function testHandlesInvalidResponse()
  {
    // Assert that if the request returns something that doesn't contain bibIds
    // an APIException is thrown
    $this->expectException(APIException::class);

    $instance = new MockBibByItem('missingitem');
  }

  public function testParsesBibId()
  {
    // Assert that the model extract the bibId (5678) from the fixture response
    $instance = new MockBibByItem('1234');

    self::assertSame($instance->bib_id, '5678');
  }
}
