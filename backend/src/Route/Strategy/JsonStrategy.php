<?php

declare(strict_types=1);

namespace TaskManager\Route\Strategy;

use League\Route\Route;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TaskManager\Middleware\Exception\NotAuthorizedException;
use TaskManager\Response\ErrorResponse;
use const FILTER_VALIDATE_INT;

final class JsonStrategy extends \League\Route\Strategy\JsonStrategy
{
    public function __construct(
        private readonly LoggerInterface $logger,
        ResponseFactoryInterface $responseFactory,
        int $jsonFlags = 0,
    ) {
        parent::__construct($responseFactory, $jsonFlags);
    }

    #[Override]
    public function invokeRouteCallable(Route $route, ServerRequestInterface $request): ResponseInterface
    {
        $controller = $route->getCallable($this->getContainer());

        $vars = array_map(
            fn (string $item): int|string => filter_var($item, FILTER_VALIDATE_INT) !== false ? (int) $item : $item,
            $route->getVars(),
        );

        /** @var ResponseInterface $response */
        $response = $controller($request, ...$vars);

        if ($this->isJsonSerializable($response)) {
            $body = json_encode($response, $this->jsonFlags);
            if ($body === false) {
                throw new \RuntimeException(json_last_error_msg(), json_last_error());
            }

            $response = $this->responseFactory->createResponse();
            $response->getBody()->write($body);
        }

        return $this->decorateResponse($response);
    }

    #[Override]
    public function getThrowableHandler(): MiddlewareInterface
    {
        return new class ($this->logger) implements MiddlewareInterface {
            public function __construct(private readonly LoggerInterface $logger)
            {
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                try {
                    return $handler->handle($request);
                } catch (\Throwable $exception) {
                    if ($exception instanceof NotAuthorizedException) {
                        $this->logger->warning($exception->getMessage(), ['exception' => $exception]);
                    } else {
                        $this->logger->error($exception->getMessage(), ['exception' => $exception]);
                    }

                    return ErrorResponse::fromException($exception);
                }
            }
        };
    }
}
