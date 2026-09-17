<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Controller\Admin\AdminWorkspaceController;
use Ukolio\Model\Entity\Enum\SystemRoleEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\StatusRepository;
use Ukolio\Model\Repository\WorkflowRepository;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(AdminWorkspaceController::class)]
final class AdminWorkspaceControllerTest extends IntegrationTestCase
{
	public function testWorkspaceListCarriesProjectAndTaskCounts(): void
	{
		$sysAdmin = Fixture::createUser(email: 'root@example.com', systemRole: SystemRoleEnum::SystemAdmin);
		$owner = Fixture::createUser(email: 'owner@example.com');
		$workspace = Fixture::createWorkspace($owner, 'Counted Workspace');

		$first = Fixture::createProject($owner, $workspace, 'First');
		Fixture::createProject($owner, $workspace, 'Second');
		$this->createTask($owner, $first->id, 'Task one');
		$this->createTask($owner, $first->id, 'Task two');

		// A second workspace must not bleed into the first one's counts.
		$otherOwner = Fixture::createUser(email: 'other-owner@example.com');
		$otherWorkspace = Fixture::createWorkspace($otherOwner, 'Other Workspace');
		$otherProject = Fixture::createProject($otherOwner, $otherWorkspace, 'Elsewhere');
		$this->createTask($otherOwner, $otherProject->id, 'Unrelated');

		$response = $this->request('GET', '/api/admin/workspaces', authenticatedAs: $sysAdmin);
		self::assertSame(200, $response->getStatusCode());

		$row = $this->rowFor($this->jsonList($response), $workspace->id);
		self::assertSame(2, $row['projectCount']);
		self::assertSame(2, $row['taskCount']);

		$otherRow = $this->rowFor($this->jsonList($response), $otherWorkspace->id);
		self::assertSame(1, $otherRow['projectCount']);
		self::assertSame(1, $otherRow['taskCount']);
	}

	public function testEmptyWorkspaceReportsZeroCounts(): void
	{
		$sysAdmin = Fixture::createUser(email: 'root@example.com', systemRole: SystemRoleEnum::SystemAdmin);
		$owner = Fixture::createUser(email: 'owner@example.com');
		$workspace = Fixture::createWorkspace($owner, 'Empty Workspace');

		$response = $this->request('GET', '/api/admin/workspaces/' . $workspace->id, authenticatedAs: $sysAdmin);
		self::assertSame(200, $response->getStatusCode());

		$detail = $this->jsonBody($response);
		$row = $detail['workspace'];
		assert(is_array($row));
		self::assertSame(0, $row['projectCount']);
		self::assertSame(0, $row['taskCount']);
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return array<string, mixed>
	 */
	private function rowFor(array $rows, int $workspaceId): array
	{
		foreach ($rows as $row) {
			if ($row['id'] === $workspaceId) {
				return $row;
			}
		}

		self::fail('Workspace ' . $workspaceId . ' missing from the admin list.');
	}

	private function createTask(User $author, int $projectId, string $name): void
	{
		$workflowRepo = $this->container->get(WorkflowRepository::class);
		assert($workflowRepo instanceof WorkflowRepository);
		$workflow = $workflowRepo->findByProject($projectId);
		assert($workflow !== null);

		$statusRepo = $this->container->get(StatusRepository::class);
		assert($statusRepo instanceof StatusRepository);
		$statusId = null;
		foreach ($statusRepo->findByWorkflow($workflow->id) as $status) {
			$statusId = $status->id;
			break;
		}
		assert($statusId !== null);

		$response = $this->request(
			'POST',
			'/api/projects/' . $projectId . '/tasks',
			body: ['statusId' => $statusId, 'name' => $name],
			authenticatedAs: $author,
		);
		self::assertSame(200, $response->getStatusCode());
	}
}
