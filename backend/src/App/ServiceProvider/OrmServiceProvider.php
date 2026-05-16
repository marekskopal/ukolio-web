<?php

declare(strict_types=1);

namespace TaskManager\App\ServiceProvider;

use League\Container\Container;
use League\Container\ServiceProvider\AbstractServiceProvider;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\ORM;
use MarekSkopal\ORM\Repository\RepositoryInterface;
use TaskManager\Model\Entity\Event;
use TaskManager\Model\Entity\Project;
use TaskManager\Model\Entity\Status;
use TaskManager\Model\Entity\Task;
use TaskManager\Model\Entity\User;
use TaskManager\Model\Entity\Workflow;
use TaskManager\Model\Repository\EventRepository;
use TaskManager\Model\Repository\ProjectRepository;
use TaskManager\Model\Repository\StatusRepository;
use TaskManager\Model\Repository\TaskRepository;
use TaskManager\Model\Repository\UserRepository;
use TaskManager\Model\Repository\WorkflowRepository;
use TaskManager\Service\Dbal\DbContext;

final class OrmServiceProvider extends AbstractServiceProvider
{
    public function __construct(private readonly DbContext $dbContext)
    {
    }

    public function provides(string $id): bool
    {
        return in_array($id, [
            DatabaseInterface::class,
            ORM::class,
            UserRepository::class,
            ProjectRepository::class,
            WorkflowRepository::class,
            StatusRepository::class,
            TaskRepository::class,
            EventRepository::class,
        ], true);
    }

    public function register(): void
    {
        $container = $this->getContainer();
        assert($container instanceof Container);

        $container->add(DatabaseInterface::class, fn () => $this->dbContext->getDatabase());
        $container->add(ORM::class, $this->dbContext->getOrm());

        $orm = $this->dbContext->getOrm();

        $this->addRepository($container, $orm, UserRepository::class, User::class);
        $this->addRepository($container, $orm, ProjectRepository::class, Project::class);
        $this->addRepository($container, $orm, WorkflowRepository::class, Workflow::class);
        $this->addRepository($container, $orm, StatusRepository::class, Status::class);
        $this->addRepository($container, $orm, TaskRepository::class, Task::class);
        $this->addRepository($container, $orm, EventRepository::class, Event::class);
    }

    /**
     * @template TEntity of object
     * @param class-string<RepositoryInterface<TEntity>> $repositoryClass
     * @param class-string<TEntity> $entityClass
     */
    private function addRepository(Container $container, ORM $orm, string $repositoryClass, string $entityClass): void
    {
        $repository = $orm->getRepository($entityClass);
        $container->add($repositoryClass, fn () => $repository);
    }
}
