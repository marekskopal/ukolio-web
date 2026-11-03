<?php

declare(strict_types=1);

namespace Ukolio\Tests\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Mcp\Tool\ProjectTools;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Service\Actor\ActorContextInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(ProjectTools::class)]
final class ProjectToolsTest extends IntegrationTestCase
{
	public function testListAndCreateProjectsViaMcp(): void
	{
		$user = Fixture::createUser();
		Fixture::createWorkspace($user);

		$tools = $this->bootMcpAs($user);

		self::assertCount(0, $tools->listProjects()->projects);

		$created = $tools->createProject('Alpha', 'desc');
		self::assertSame('Alpha', $created->name);

		$list = $tools->listProjects();
		self::assertCount(1, $list->projects);
		self::assertSame('Alpha', $list->projects[0]->name);
	}

	public function testFindProjectByNameIsCaseInsensitive(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		Fixture::createProject($user, $workspace, 'Apollo');

		$tools = $this->bootMcpAs($user);

		self::assertNotNull($tools->findProjectByName('apollo'));
		self::assertNull($tools->findProjectByName('zenith'));
	}

	public function testGetProjectThrowsWhenNotFound(): void
	{
		$user = Fixture::createUser();
		Fixture::createWorkspace($user);

		$tools = $this->bootMcpAs($user);

		$this->expectException(RuntimeException::class);
		$tools->getProject(9999);
	}

	public function testUpdateProjectRenamesAndRegeneratesPrefix(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace, 'Apollo');

		$tools = $this->bootMcpAs($user);
		$updated = $tools->updateProject($project->id, name: '  Zenith  ');

		self::assertSame('Zenith', $updated->name);
		self::assertSame('Z', $updated->prefix);
		self::assertSame('Zenith', $tools->getProject($project->id)->name);
	}

	public function testUpdateProjectKeepsOmittedFieldsAndClearsEmptyDescription(): void
	{
		$user = Fixture::createUser();
		Fixture::createWorkspace($user);
		$tools = $this->bootMcpAs($user);
		$project = $tools->createProject('Apollo', 'Original');

		$updated = $tools->updateProject($project->id, description: 'Changed');
		self::assertSame('Apollo', $updated->name);
		self::assertSame('Changed', $updated->description);

		$renamed = $tools->updateProject($project->id, name: 'Artemis');
		self::assertSame('Changed', $renamed->description);

		self::assertNull($tools->updateProject($project->id, description: '')->description);
	}

	public function testUpdateProjectRejectsBlankName(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace, 'Apollo');

		$tools = $this->bootMcpAs($user);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('name is required');
		$tools->updateProject($project->id, name: '   ');
	}

	public function testUpdateProjectThrowsWhenNotFound(): void
	{
		$user = Fixture::createUser();
		Fixture::createWorkspace($user);

		$tools = $this->bootMcpAs($user);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('Project 9999 not found');
		$tools->updateProject(9999, name: 'Nope');
	}

	public function testMemberCannotUpdateProject(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace, 'Apollo');
		$member = Fixture::createUser(email: 'member@example.com');
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);

		$workspaceProvider = AppHarness::container()->get(WorkspaceProviderInterface::class);
		assert($workspaceProvider instanceof WorkspaceProviderInterface);
		$workspaceProvider->switchCurrentWorkspace($member, $workspace);

		$tools = $this->bootMcpAs($member);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('do not have permission');
		$tools->updateProject($project->id, name: 'Hijacked');
	}

	public function testDeleteProjectRemovesIt(): void
	{
		$user = Fixture::createUser();
		$workspace = Fixture::createWorkspace($user);
		$project = Fixture::createProject($user, $workspace);

		$tools = $this->bootMcpAs($user);
		$tools->deleteProject($project->id);

		self::assertCount(0, $tools->listProjects()->projects);
	}

	private function bootMcpAs(User $user): ProjectTools
	{
		$ctx = AppHarness::container()->get(McpUserContextInterface::class);
		assert($ctx instanceof McpUserContextInterface);
		$ctx->setUser($user);

		// Flip ActorContext to Agent so tasks/events get marked agent-created.
		$actor = AppHarness::container()->get(ActorContextInterface::class);
		assert($actor instanceof ActorContextInterface);
		$actor->setAgent('test-client', 'Test Client');

		$tools = AppHarness::container()->get(ProjectTools::class);
		assert($tools instanceof ProjectTools);
		return $tools;
	}
}
