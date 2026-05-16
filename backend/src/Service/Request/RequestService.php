<?php

declare(strict_types=1);

namespace TaskManager\Service\Request;

use Nette\Utils\Json;
use Psr\Http\Message\ServerRequestInterface;
use TaskManager\Dto\ArrayFactoryInterface;
use TaskManager\Middleware\AuthorizationMiddleware;
use TaskManager\Model\Entity\User;

final readonly class RequestService implements RequestServiceInterface
{
    public function getUser(ServerRequestInterface $request): User
    {
        $user = $request->getAttribute(AuthorizationMiddleware::AttributeUser);
        assert($user instanceof User);
        return $user;
    }

    /** @return array<mixed> */
    public function getRequestBody(ServerRequestInterface $request): array
    {
        /** @var array<mixed> $decodedBody */
        $decodedBody = Json::decode($request->getBody()->getContents(), forceArrays: true);
        return $decodedBody;
    }

    /**
     * @template T of ArrayFactoryInterface
     * @param class-string<T> $dtoClass
     * @return T
     */
    public function getRequestBodyDto(ServerRequestInterface $request, string $dtoClass): object
    {
        return $dtoClass::fromArray($this->getRequestBody($request));
    }
}
