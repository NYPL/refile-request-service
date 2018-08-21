<?php
namespace NYPL\Test\Controller;

use NYPL\Services\Controller\RefileRequestController;
use NYPL\Services\Model\RefileRequest\RefileRequest;
use NYPL\Services\Model\Response\RefileRequestResponse;
use NYPL\Services\Test\Mocks\MockConfig;
use NYPL\Services\Test\Mocks\MockService;
use NYPL\Starter\APIException;
use NYPL\Starter\APILogger;
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
                $data = json_decode(file_get_contents(__DIR__ . '/../Stubs/validRefileRequest.json'), true);

                $refileRequest = new RefileRequest($data);

                return $this->getResponse()->withJson(
                    new RefileRequestResponse($refileRequest)
                );
            }

            public function invalidRefileRequest()
            {
                $data = json_decode(file_get_contents(__DIR__ . '/../Stubs/invalidRefileRequest.json'), true);

                $refileRequest = new RefileRequest($data);

                try {
                    $refileRequest->validatePostData();
                } catch (APIException $exception) {
                    APILogger::addDebug($exception->getMessage());
                    return $this->invalidRequestResponse($exception);
                }
            }

            public function getRefileRequests()
            {
                $data = json_decode(file_get_contents(__DIR__ . '/../Stubs/get_refile_request_response_200.json'), true);

                $refileRequests = new RefileRequest($data);

                return $this->getResponse()->withJson(
                    new RefileRequestResponse($refileRequests)
                );
            }

            public function getRefileErrors()
            {
                $data = json_decode(file_get_contents(__DIR__ . '/../Stubs/get_refile_request_response_200.json'), true);

                $refileRequests = new RefileRequest($data);

                return $this->getResponse()->withJson(
                    new RefileRequestResponse($refileRequests)
                );
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
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @covers NYPL\Services\Controller\RefileRequestController::createRefileRequest()
     */
    public function testInvalidRequestReturns400()
    {
        $controller = $this->fakeRefileRequestController;

        $response = $controller->invalidRefileRequest();

        self::assertInstanceOf('Slim\Http\Response', $response);
        self::assertSame(400, $response->getStatusCode());
    }


    /**
     * @covers NYPL\Services\Controller\RefileRequestController::getRefileErrors()
     */
    public function testGetRefileErrorsReturns200()
    {
        $controller = $this->fakeRefileRequestController;

        $response = $controller->getRefileErrors();

        self::assertInstanceOf('Slim\Http\Response', $response);
        self::assertSame(200, $response->getStatusCode());
    }
}
