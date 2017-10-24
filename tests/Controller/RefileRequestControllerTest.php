<?php
namespace NYPL\Test\Controller;

use NYPL\Services\Controller\RefileRequestController;
use NYPL\Services\Test\Mocks\MockConfig;
use NYPL\Services\Test\Mocks\MockService;
use PHPUnit\Framework\TestCase;

class RefileRequestControllerTest extends TestCase
{
    public $fakeRefileRequestController;

    public function setUp()
    {
        parent::setUp();
        MockConfig::initialize(__DIR__ . '/../Mocks/');
        MockService::setMockContainer();
        $this->mockContainer = MockService::$mockContainer;

        $this->fakeRefileRequestController = new class(MockService::getMockContainer(), 0) extends RefileRequestController {

            public $container;
            public $cacheSeconds;

            public function __construct(\Slim\Container $container, $cacheSeconds)
            {
                $this->setUseJobService(1);
                parent::__construct($container, $cacheSeconds);
            }

            public function createRefileRequest()
            {
                return parent::createRefileRequest();
            }
        };
    }

    /**
     * @covers NYPL\Services\Controller\RefileRequestController::createRefileRequest()
     */
    public function testCreatesRefileModelFromRequest()
    {
        $controller = $this->fakeRefileRequestController;

        $response = $controller->createRefileRequest();

        self::assertInstanceOf('Slim\Http\Response', $response);
    }

    /**
     * @covers NYPL\Services\Controller\RefileRequestController::createRefileRequest()
     */
    public function testMisconfigurationThrowsException()
    {
        $controller = $this->fakeRefileRequestController;

        $response = $controller->createRefileRequest();

        self::assertSame(500, $response->getStatusCode());
    }
}
