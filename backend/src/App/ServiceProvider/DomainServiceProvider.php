<?php

declare(strict_types=1);

namespace TaskManager\App\ServiceProvider;

use League\Container\ServiceProvider\AbstractServiceProvider;
use TaskManager\Service\Provider\EventProvider;
use TaskManager\Service\Provider\EventProviderInterface;
use TaskManager\Service\Provider\ProjectProvider;
use TaskManager\Service\Provider\ProjectProviderInterface;
use TaskManager\Service\Provider\StatusProvider;
use TaskManager\Service\Provider\StatusProviderInterface;
use TaskManager\Service\Provider\TaskProvider;
use TaskManager\Service\Provider\TaskProviderInterface;
use TaskManager\Service\Provider\UserProvider;
use TaskManager\Service\Provider\UserProviderInterface;
use TaskManager\Service\Provider\WorkflowProvider;
use TaskManager\Service\Provider\WorkflowProviderInterface;
use TaskManager\Service\Request\RequestService;
use TaskManager\Service\Request\RequestServiceInterface;

final class DomainServiceProvider extends AbstractServiceProvider
{
    public function provides(string $id): bool
    {
        return in_array($id, [
            RequestServiceInterface::class,
            UserProviderInterface::class,
            ProjectProviderInterface::class,
            WorkflowProviderInterface::class,
            StatusProviderInterface::class,
            TaskProviderInterface::class,
            EventProviderInterface::class,
        ], true);
    }

    public function register(): void
    {
        $c = $this->getContainer();
        $c->add(RequestServiceInterface::class, RequestService::class);
        $c->add(UserProviderInterface::class, UserProvider::class);
        $c->add(EventProviderInterface::class, EventProvider::class);
        $c->add(StatusProviderInterface::class, StatusProvider::class);
        $c->add(WorkflowProviderInterface::class, WorkflowProvider::class);
        $c->add(ProjectProviderInterface::class, ProjectProvider::class);
        $c->add(TaskProviderInterface::class, TaskProvider::class);
    }
}
