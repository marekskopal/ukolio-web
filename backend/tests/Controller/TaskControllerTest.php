<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Controller\TaskController;
use Ukolio\Model\Repository\StatusRepository;
use Ukolio\Model\Repository\WorkflowRepository;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(TaskController::class)]
final class TaskControllerTest extends IntegrationTestCase
{
	public function testCreateListAndGetTaskRoundTrip(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);

		$startStatus = $this->firstStatusId($project->id);

		$create = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: [
				'statusId' => $startStatus,
				'name' => 'Write tests',
				'description' => 'Cover the codebase',
				'priority' => 'High',
				'dueDate' => '2026-06-01',
			],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $create->getStatusCode());
		$task = $this->jsonBody($create);
		self::assertSame('Write tests', $task['name']);
		self::assertSame('High', $task['priority']);
		self::assertNotEmpty($task['code']);
		$taskId = self::intField($task['id']);
		$taskCode = self::stringField($task['code']);

		// List under project
		$list = $this->request('GET', '/api/projects/' . $project->id . '/tasks', authenticatedAs: $owner);
		self::assertCount(1, $this->jsonList($list));

		// Get by numeric ID (routes accept either int ID or PREFIX-N code)
		$getById = $this->request('GET', '/api/tasks/' . $taskId, authenticatedAs: $owner);
		self::assertSame(200, $getById->getStatusCode());

		// Get by code form
		$getByCode = $this->request('GET', '/api/tasks/' . $taskCode, authenticatedAs: $owner);
		self::assertSame(200, $getByCode->getStatusCode());

		// Workspace-wide listing returns same task
		$wsList = $this->request('GET', '/api/tasks', authenticatedAs: $owner);
		self::assertSame(200, $wsList->getStatusCode());
		$payload = $this->jsonBody($wsList);
		self::assertSame(1, $payload['count']);
		$payloadTasks = $payload['tasks'];
		self::assertIsArray($payloadTasks);
		self::assertCount(1, $payloadTasks);
	}

	public function testMoveTaskBetweenStatuses(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);

		[$todoId, $inProgressId] = $this->statusIds($project->id);

		$create = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: ['statusId' => $todoId, 'name' => 'Move me', 'description' => null, 'priority' => 'Medium'],
			authenticatedAs: $owner,
		);
		$code = self::stringField($this->jsonBody($create)['code']);

		$move = $this->request(
			'PUT',
			'/api/tasks/' . $code . '/move',
			body: ['statusId' => $inProgressId, 'position' => 0],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $move->getStatusCode());
		self::assertSame($inProgressId, $this->jsonBody($move)['statusId']);
	}

	public function testWorkspaceListPaginationAndStatusFilter(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		[$todoId, $inProgressId] = $this->statusIds($project->id);

		for ($i = 0; $i < 4; $i++) {
			$this->request(
				'POST',
				'/api/projects/' . $project->id . '/tasks',
				body: ['statusId' => $i % 2 === 0 ? $todoId : $inProgressId, 'name' => 'T' . $i, 'description' => null, 'priority' => 'Medium'],
				authenticatedAs: $owner,
			);
		}

		$filtered = $this->request('GET', '/api/tasks?statusIds=' . $todoId . '&limit=10', authenticatedAs: $owner);
		self::assertSame(200, $filtered->getStatusCode());
		self::assertSame(2, $this->jsonBody($filtered)['count']);

		$paged = $this->request('GET', '/api/tasks?limit=2&offset=0', authenticatedAs: $owner);
		$pagedBody = $this->jsonBody($paged);
		$pagedTasks = $pagedBody['tasks'];
		self::assertIsArray($pagedTasks);
		self::assertCount(2, $pagedTasks);
		self::assertSame(4, $pagedBody['count']);
	}

	public function testDeleteTaskRemovesIt(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$todoId = $this->firstStatusId($project->id);

		$create = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: ['statusId' => $todoId, 'name' => 'Doomed', 'description' => null, 'priority' => 'Low'],
			authenticatedAs: $owner,
		);
		$code = self::stringField($this->jsonBody($create)['code']);

		$delete = $this->request('DELETE', '/api/tasks/' . $code, authenticatedAs: $owner);
		self::assertSame(200, $delete->getStatusCode());

		$get = $this->request('GET', '/api/tasks/' . $code, authenticatedAs: $owner);
		self::assertSame(404, $get->getStatusCode());
	}

	/** @return array{0:int,1:int} */
	private function statusIds(int $projectId): array
	{
		$workflowRepo = $this->container->get(WorkflowRepository::class);
		assert($workflowRepo instanceof WorkflowRepository);
		$workflow = $workflowRepo->findByProject($projectId);
		assert($workflow !== null);

		$statusRepo = $this->container->get(StatusRepository::class);
		assert($statusRepo instanceof StatusRepository);
		$statuses = [];
		foreach ($statusRepo->findByWorkflow($workflow->id) as $status) {
			$statuses[] = $status->id;
		}
		return [$statuses[0], $statuses[1]];
	}

	private function firstStatusId(int $projectId): int
	{
		return $this->statusIds($projectId)[0];
	}
}
