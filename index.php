<?php
namespace NYPL\Services;

use NYPL\Starter\Service;
use NYPL\Starter\Config;
use NYPL\Starter\ErrorHandler;
use NYPL\Services\Controller\RefileRequestController;

require __DIR__ . '/vendor/autoload.php';

try {
    Config::initialize(__DIR__ . '/config');

    print "This is live";

    $container = new ServiceContainer();

    $service = new Service($container);

    $service->get('/docs/refile-requests', Swagger::class);

    $service->post('/api/v0.1/recap/refile-requests', RefileRequestController::class . ':createRefileRequest');

    $service->post('/api/v0.1/recap/refile-requests-sync', RefileRequestController::class . ':createRefileRequestSync');

    $service->get('/api/v0.1/recap/refile-requests', RefileRequestController::class . ':getRefileRequests');

    $service->get('/api/v0.1/recap/refile-errors', RefileRequestController::class. ':getRefileErrors');

    $service->run();
} catch (\Exception $exception) {
    ErrorHandler::processShutdownError($exception->getMessage(), $exception);
}
