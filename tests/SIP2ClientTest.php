<?php
namespace NYPL\Test;

use NYPL\Services\SIP2Client;
use NYPL\Services\Test\Mocks\MockConfig;
use PHPUnit\Framework\TestCase;

class SIP2ClientTest extends TestCase
{

    public function setUp()
    {
        MockConfig::initialize(__DIR__ . '/../');
    }

    public function tearDown()
    {
        $this->resetConfig('SIP2_HOSTNAME', 'nypl-sierra-test.iii.com');
    }

    public function resetConfig($name, $value)
    {
        $data = file(__DIR__ . '/Mocks/mock.env');
        $data = array_map(function($data) use ($name, $value) {
            return stristr($data, $name) ? "{$name}={$value}\n" : $data;
        }, $data);
        file_put_contents(__DIR__ . '/Mocks/mock.env', $data);
    }

    /**
     * @covers NYPL\Services\SIP2Client::initializeSip2Client()
     */
    public function testConnectionFailure()
    {
        $client = new SIP2Client();
        self::assertClassHasAttribute('sip2Client', get_class($client));
    }
}
