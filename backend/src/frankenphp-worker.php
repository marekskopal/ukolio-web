<?php

declare(strict_types=1);

namespace TaskManager;

require_once __DIR__ . '/../vendor/autoload.php';

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Psr\Log\LoggerInterface;
use TaskManager\App\ApplicationFactory;
use TaskManager\Response\ErrorResponse;

$application = ApplicationFactory::create();

$logger = $application->container->get(LoggerInterface::class);
assert($logger instanceof LoggerInterface);

$emitter = new SapiEmitter();

$handler = static function () use ($application, $logger, $emitter): void {
    try {
        $request = ServerRequestFactory::fromGlobals();
        $response = $application->handler->handle($request);
        $emitter->emit($response);
    } catch (\Throwable $e) {
        $logger->error($e->getMessage(), ['exception' => $e]);
        $emitter->emit(ErrorResponse::fromException($e));
    }
};

while (frankenphp_handle_request($handler)) {
    $application->dbContext->getOrm()->getEntityCache()->clear();
    gc_collect_cycles();
}
