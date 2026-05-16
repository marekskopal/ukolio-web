<?php

declare(strict_types=1);

namespace TaskManager\App\ServiceProvider;

use League\Container\ServiceProvider\AbstractServiceProvider;
use TaskManager\Service\Authentication\AuthenticationService;
use TaskManager\Service\Authentication\AuthenticationServiceInterface;

final class AuthenticationServiceProvider extends AbstractServiceProvider
{
    public function provides(string $id): bool
    {
        return $id === AuthenticationServiceInterface::class;
    }

    public function register(): void
    {
        $this->getContainer()->add(AuthenticationServiceInterface::class, AuthenticationService::class);
    }
}
