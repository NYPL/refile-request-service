<?php
namespace NYPL\Test\Model\RefileRequest;

use NYPL\Services\Model\RefileRequest\RefileRequest;
use PHPUnit\Framework\TestCase;

class RefileRequestTest extends TestCase
{
    public $refileRequest;
    public $schema;

    public function setUp()
    {
        $this->refileRequest = new RefileRequest();
        $this->schema = $this->refileRequest->getSchema();
    }

    /**
     * @covers NYPL\Services\Model\RefileRequest\RefileRequest::getSchema()
     */
    public function testIfSchemaHasValidKeys()
    {
        self::assertArrayHasKey('name', $this->schema);
        self::assertArrayHasKey('type', $this->schema);
        self::assertArrayHasKey('fields', $this->schema);
    }

    public function testIfObjectContainsSchemaFields()
    {
        $fields = $this->schema['fields'];

        foreach ($fields as $field) {
            self::assertClassHasAttribute($field['name'], 'NYPL\Services\Model\RefileRequest\RefileRequest');
        }
    }

    /**
     * @covers NYPL\Services\Model\RefileRequest\RefileRequest::setId()
     * @covers NYPL\Services\Model\RefileRequest\RefileRequest::getId()
     * @covers NYPL\Services\Model\RefileRequest\RefileRequest::setItemBarcode()
     * @covers NYPL\Services\Model\RefileRequest\RefileRequest::getItemBarcode()
     * @covers NYPL\Services\Model\RefileRequest\RefileRequest::setJobId()
     * @covers NYPL\Services\Model\RefileRequest\RefileRequest::validatePostData()
     */
    public function testIfPostedDataIsValid()
    {
        $data = json_decode(file_get_contents(__DIR__ . '/../../Stubs/validRefileRequest.json'), true);
        $newRequest = new RefileRequest($data);

        self::assertInstanceOf('\NYPL\Services\Model\RefileRequest\RefileRequest', $newRequest);
    }

    /**
     * @expectedException \NYPL\Starter\APIException
     *
     * @covers NYPL\Services\Model\RefileRequest\RefileRequest::validatePostData()
     */
    public function testIfPostedDataThrowsException()
    {
        $this->refileRequest->validatePostData();
    }
}
