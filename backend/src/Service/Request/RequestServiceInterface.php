<?php

declare(strict_types=1);

namespace TaskManager\Service\Request;

use Psr\Http\Message\ServerRequestInterface;
use TaskManager\Dto\ArrayFactoryInterface;
use TaskManager\Model\Entity\User;

interface RequestServiceInterface
{
    public function getUser(ServerRequestInterface $request): User;

    /** @return array<mixed> */
    public function getRequestBody(ServerRequestInterface $request): array;

    /**
     * @template T of ArrayFactoryInterface
     * @param class-string<T> $dtoClass
     * @return T
     */
    public function getRequestBodyDto(ServerRequestInterface $request, string $dtoClass): object;
}
