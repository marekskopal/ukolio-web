<?php

declare(strict_types=1);

namespace Ukolio\App\ServiceProvider;

use League\Container\ServiceProvider\AbstractServiceProvider;
use Psr\Log\LoggerInterface;
use Ukolio\Mcp\McpUserContext;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Mcp\Server\UkolioServer;
use Ukolio\OAuth\AuthorizationService;
use Ukolio\OAuth\AuthorizationServiceInterface;
use Ukolio\OAuth\ClientService;
use Ukolio\OAuth\ClientServiceInterface;
use Ukolio\Service\Provider\EventProvider;
use Ukolio\Service\Provider\EventProviderInterface;
use Ukolio\Service\Provider\InvitationProvider;
use Ukolio\Service\Provider\InvitationProviderInterface;
use Ukolio\Service\Provider\ProjectProvider;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\StatusProvider;
use Ukolio\Service\Provider\StatusProviderInterface;
use Ukolio\Service\Provider\TaskProvider;
use Ukolio\Service\Provider\TaskProviderInterface;
use Ukolio\Service\Provider\UserProvider;
use Ukolio\Service\Provider\UserProviderInterface;
use Ukolio\Service\Provider\WorkflowProvider;
use Ukolio\Service\Provider\WorkflowProviderInterface;
use Ukolio\Service\Provider\WorkspaceProvider;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestService;
use Ukolio\Service\Request\RequestServiceInterface;
use Ukolio\Service\Translator\TranslatorService;
use Ukolio\Service\Translator\TranslatorServiceInterface;

final class DomainServiceProvider extends AbstractServiceProvider
{
	public function provides(string $id): bool
	{
		return in_array($id, [
			RequestServiceInterface::class,
			UserProviderInterface::class,
			WorkspaceProviderInterface::class,
			InvitationProviderInterface::class,
			ProjectProviderInterface::class,
			WorkflowProviderInterface::class,
			StatusProviderInterface::class,
			TaskProviderInterface::class,
			EventProviderInterface::class,
			McpUserContextInterface::class,
			UkolioServer::class,
			ClientServiceInterface::class,
			AuthorizationServiceInterface::class,
			TranslatorServiceInterface::class,
		], true);
	}

	public function register(): void
	{
		$c = $this->getContainer();
		$c->add(RequestServiceInterface::class, RequestService::class);
		$c->add(UserProviderInterface::class, UserProvider::class);
		$c->add(WorkspaceProviderInterface::class, WorkspaceProvider::class);
		$c->add(TranslatorServiceInterface::class, static fn (): TranslatorService => new TranslatorService(
			translationsDir: __DIR__ . '/../../../translations',
		));
		$c->add(InvitationProviderInterface::class, InvitationProvider::class);
		$c->add(EventProviderInterface::class, EventProvider::class);
		$c->add(StatusProviderInterface::class, StatusProvider::class);
		$c->add(WorkflowProviderInterface::class, WorkflowProvider::class);
		$c->add(ProjectProviderInterface::class, ProjectProvider::class);
		$c->add(TaskProviderInterface::class, TaskProvider::class);
		$c->add(McpUserContextInterface::class, McpUserContext::class);
		$c->add(UkolioServer::class, function () use ($c): UkolioServer {
			$logger = $c->get(LoggerInterface::class);
			assert($logger instanceof LoggerInterface);
			return new UkolioServer($c, $logger);
		});
		$c->add(ClientServiceInterface::class, ClientService::class);
		$c->add(AuthorizationServiceInterface::class, AuthorizationService::class);
	}
}
