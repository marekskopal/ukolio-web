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
use Ukolio\Service\Actor\ActorContext;
use Ukolio\Service\Actor\ActorContextInterface;
use Ukolio\Service\Auth\AdminService;
use Ukolio\Service\Auth\AdminServiceInterface;
use Ukolio\Service\Auth\CurrentUserDeletionService;
use Ukolio\Service\Auth\CurrentUserDeletionServiceInterface;
use Ukolio\Service\Auth\PermissionChecker;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Auth\UserDataExportService;
use Ukolio\Service\Auth\UserDataExportServiceInterface;
use Ukolio\Service\Provider\BulkTaskProvider;
use Ukolio\Service\Provider\BulkTaskProviderInterface;
use Ukolio\Service\Provider\EmailVerificationProvider;
use Ukolio\Service\Provider\EmailVerificationProviderInterface;
use Ukolio\Service\Provider\EventProvider;
use Ukolio\Service\Provider\EventProviderInterface;
use Ukolio\Service\Provider\FieldProvider;
use Ukolio\Service\Provider\FieldProviderInterface;
use Ukolio\Service\Provider\InvitationProvider;
use Ukolio\Service\Provider\InvitationProviderInterface;
use Ukolio\Service\Provider\PasswordResetProvider;
use Ukolio\Service\Provider\PasswordResetProviderInterface;
use Ukolio\Service\Provider\PriorityProvider;
use Ukolio\Service\Provider\PriorityProviderInterface;
use Ukolio\Service\Provider\ProjectFieldProvider;
use Ukolio\Service\Provider\ProjectFieldProviderInterface;
use Ukolio\Service\Provider\ProjectPrefixGenerator;
use Ukolio\Service\Provider\ProjectPrefixGeneratorInterface;
use Ukolio\Service\Provider\ProjectProvider;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\SavedViewProvider;
use Ukolio\Service\Provider\SavedViewProviderInterface;
use Ukolio\Service\Provider\StatusProvider;
use Ukolio\Service\Provider\StatusProviderInterface;
use Ukolio\Service\Provider\TagProvider;
use Ukolio\Service\Provider\TagProviderInterface;
use Ukolio\Service\Provider\TaskCodeResolver;
use Ukolio\Service\Provider\TaskCodeResolverInterface;
use Ukolio\Service\Provider\TaskCommentProvider;
use Ukolio\Service\Provider\TaskCommentProviderInterface;
use Ukolio\Service\Provider\TaskFieldValueProvider;
use Ukolio\Service\Provider\TaskFieldValueProviderInterface;
use Ukolio\Service\Provider\TaskFileProvider;
use Ukolio\Service\Provider\TaskFileProviderInterface;
use Ukolio\Service\Provider\TaskProvider;
use Ukolio\Service\Provider\TaskProviderInterface;
use Ukolio\Service\Provider\TaskRelationProvider;
use Ukolio\Service\Provider\TaskRelationProviderInterface;
use Ukolio\Service\Provider\TaskTagProvider;
use Ukolio\Service\Provider\TaskTagProviderInterface;
use Ukolio\Service\Provider\UserProvider;
use Ukolio\Service\Provider\UserProviderInterface;
use Ukolio\Service\Provider\WorkflowProvider;
use Ukolio\Service\Provider\WorkflowProviderInterface;
use Ukolio\Service\Provider\WorkspaceMcpClientProvider;
use Ukolio\Service\Provider\WorkspaceMcpClientProviderInterface;
use Ukolio\Service\Provider\WorkspaceProvider;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestService;
use Ukolio\Service\Request\RequestServiceInterface;
use Ukolio\Service\Task\TaskService;
use Ukolio\Service\Task\TaskServiceInterface;
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
			WorkspaceMcpClientProviderInterface::class,
			PermissionCheckerInterface::class,
			AdminServiceInterface::class,
			CurrentUserDeletionServiceInterface::class,
			UserDataExportServiceInterface::class,
			InvitationProviderInterface::class,
			PasswordResetProviderInterface::class,
			EmailVerificationProviderInterface::class,
			ProjectProviderInterface::class,
			ProjectPrefixGeneratorInterface::class,
			WorkflowProviderInterface::class,
			StatusProviderInterface::class,
			TaskProviderInterface::class,
			BulkTaskProviderInterface::class,
			TaskCodeResolverInterface::class,
			TaskCommentProviderInterface::class,
			TaskFieldValueProviderInterface::class,
			TaskFileProviderInterface::class,
			TaskRelationProviderInterface::class,
			FieldProviderInterface::class,
			ProjectFieldProviderInterface::class,
			TagProviderInterface::class,
			TaskTagProviderInterface::class,
			SavedViewProviderInterface::class,
			PriorityProviderInterface::class,
			EventProviderInterface::class,
			McpUserContextInterface::class,
			ActorContextInterface::class,
			UkolioServer::class,
			ClientServiceInterface::class,
			AuthorizationServiceInterface::class,
			TranslatorServiceInterface::class,
			TaskServiceInterface::class,
		], true);
	}

	public function register(): void
	{
		$c = $this->getContainer();
		$c->add(RequestServiceInterface::class, RequestService::class);
		$c->add(UserProviderInterface::class, UserProvider::class);
		$c->add(WorkspaceProviderInterface::class, WorkspaceProvider::class);
		$c->add(WorkspaceMcpClientProviderInterface::class, WorkspaceMcpClientProvider::class);
		$c->add(PermissionCheckerInterface::class, PermissionChecker::class);
		$c->add(AdminServiceInterface::class, AdminService::class);
		$c->add(CurrentUserDeletionServiceInterface::class, CurrentUserDeletionService::class);
		$c->add(UserDataExportServiceInterface::class, UserDataExportService::class);
		$c->add(TranslatorServiceInterface::class, static fn (): TranslatorService => new TranslatorService(
			translationsDir: __DIR__ . '/../../../translations',
		));
		$c->add(InvitationProviderInterface::class, InvitationProvider::class);
		$c->add(PasswordResetProviderInterface::class, PasswordResetProvider::class);
		$c->add(EmailVerificationProviderInterface::class, EmailVerificationProvider::class);
		$c->add(TaskServiceInterface::class, TaskService::class);
		$c->add(EventProviderInterface::class, EventProvider::class);
		$c->add(StatusProviderInterface::class, StatusProvider::class);
		$c->add(WorkflowProviderInterface::class, WorkflowProvider::class);
		$c->add(ProjectProviderInterface::class, ProjectProvider::class);
		$c->add(ProjectPrefixGeneratorInterface::class, ProjectPrefixGenerator::class);
		$c->add(TaskProviderInterface::class, TaskProvider::class);
		$c->add(BulkTaskProviderInterface::class, BulkTaskProvider::class);
		$c->add(TaskCodeResolverInterface::class, TaskCodeResolver::class);
		$c->add(TaskCommentProviderInterface::class, TaskCommentProvider::class);
		$c->add(TaskFieldValueProviderInterface::class, TaskFieldValueProvider::class);
		$c->add(TaskFileProviderInterface::class, TaskFileProvider::class);
		$c->add(TaskRelationProviderInterface::class, TaskRelationProvider::class);
		$c->add(FieldProviderInterface::class, FieldProvider::class);
		$c->add(ProjectFieldProviderInterface::class, ProjectFieldProvider::class);
		$c->add(TagProviderInterface::class, TagProvider::class);
		$c->add(TaskTagProviderInterface::class, TaskTagProvider::class);
		$c->add(SavedViewProviderInterface::class, SavedViewProvider::class);
		$c->add(PriorityProviderInterface::class, PriorityProvider::class);
		$c->add(McpUserContextInterface::class, McpUserContext::class);
		$c->add(ActorContextInterface::class, ActorContext::class);
		$c->add(UkolioServer::class, function () use ($c): UkolioServer {
			$logger = $c->get(LoggerInterface::class);
			assert($logger instanceof LoggerInterface);
			return new UkolioServer($c, $logger);
		});
		$c->add(ClientServiceInterface::class, ClientService::class);
		$c->add(AuthorizationServiceInterface::class, AuthorizationService::class);
	}
}
