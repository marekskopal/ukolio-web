<?php

declare(strict_types=1);

namespace Ukolio\App\ServiceProvider;

use League\Container\Container;
use League\Container\ServiceProvider\AbstractServiceProvider;
use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\ORM;
use MarekSkopal\ORM\Repository\RepositoryInterface;
use Ukolio\Model\Entity\Event;
use Ukolio\Model\Entity\Invitation;
use Ukolio\Model\Entity\OAuthAuthorization;
use Ukolio\Model\Entity\OAuthClient;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Status;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workflow;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Entity\WorkspaceUser;
use Ukolio\Model\Repository\EventRepository;
use Ukolio\Model\Repository\InvitationRepository;
use Ukolio\Model\Repository\OAuthAuthorizationRepository;
use Ukolio\Model\Repository\OAuthClientRepository;
use Ukolio\Model\Repository\ProjectRepository;
use Ukolio\Model\Repository\StatusRepository;
use Ukolio\Model\Repository\TaskRepository;
use Ukolio\Model\Repository\UserRepository;
use Ukolio\Model\Repository\WorkflowRepository;
use Ukolio\Model\Repository\WorkspaceRepository;
use Ukolio\Model\Repository\WorkspaceUserRepository;
use Ukolio\Service\Dbal\DbContext;

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
			WorkspaceRepository::class,
			WorkspaceUserRepository::class,
			InvitationRepository::class,
			ProjectRepository::class,
			WorkflowRepository::class,
			StatusRepository::class,
			TaskRepository::class,
			EventRepository::class,
			OAuthClientRepository::class,
			OAuthAuthorizationRepository::class,
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
		$this->addRepository($container, $orm, WorkspaceRepository::class, Workspace::class);
		$this->addRepository($container, $orm, WorkspaceUserRepository::class, WorkspaceUser::class);
		$this->addRepository($container, $orm, InvitationRepository::class, Invitation::class);
		$this->addRepository($container, $orm, ProjectRepository::class, Project::class);
		$this->addRepository($container, $orm, WorkflowRepository::class, Workflow::class);
		$this->addRepository($container, $orm, StatusRepository::class, Status::class);
		$this->addRepository($container, $orm, TaskRepository::class, Task::class);
		$this->addRepository($container, $orm, EventRepository::class, Event::class);
		$this->addRepository($container, $orm, OAuthClientRepository::class, OAuthClient::class);
		$this->addRepository($container, $orm, OAuthAuthorizationRepository::class, OAuthAuthorization::class);
	}

	/**
	 * @param class-string<RepositoryInterface<TEntity>> $repositoryClass
	 * @param class-string<TEntity> $entityClass
	 * @template TEntity of object
	 */
	private function addRepository(Container $container, ORM $orm, string $repositoryClass, string $entityClass): void
	{
		$repository = $orm->getRepository($entityClass);
		$container->add($repositoryClass, fn () => $repository);
	}
}
