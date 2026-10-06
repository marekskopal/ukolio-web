<?php

declare(strict_types=1);

namespace Ukolio\Tests\Service\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Ukolio\Model\Entity\Enum\FieldTypeEnum;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Repository\StatusRepository;
use Ukolio\Model\Repository\TaskRepository;
use Ukolio\Model\Repository\WorkflowRepository;
use Ukolio\Service\Provider\FieldProviderInterface;
use Ukolio\Service\Provider\ProjectFieldProviderInterface;
use Ukolio\Service\Provider\TaskFieldValueProviderInterface;
use Ukolio\Service\Provider\TaskProvider;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

/**
 * TaskProvider::deleteTask removes the task and every row hanging off it in one unit-of-work flush;
 * a subtask linked to it survives as a top-level task.
 */
#[CoversClass(TaskProvider::class)]
final class TaskDeletionCascadeTest extends IntegrationTestCase
{
	public function testDeleteTaskRemovesAllDependentRowsAndOrphansSubtasks(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);

		$tagId = self::intField($this->jsonBody($this->request(
			'POST',
			'/api/workspaces/' . $workspace->id . '/tags',
			body: ['name' => 'bug', 'color' => '#ff0000'],
			authenticatedAs: $owner,
		))['id']);

		$taskId = self::intField($this->jsonBody($this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: [
				'statusId' => $this->firstStatusId($project->id),
				'name' => 'Doomed',
				'description' => null,
				'priority' => 'Low',
				'tagIds' => [$tagId],
			],
			authenticatedAs: $owner,
		))['id']);

		$fieldProvider = $this->container->get(FieldProviderInterface::class);
		assert($fieldProvider instanceof FieldProviderInterface);
		$field = $fieldProvider->createField($owner, $workspace, 'Env', FieldTypeEnum::Text, false, null, null);
		$projectFieldProvider = $this->container->get(ProjectFieldProviderInterface::class);
		assert($projectFieldProvider instanceof ProjectFieldProviderInterface);
		$projectFieldProvider->setProjectFields($owner, $project, [$field->id]);
		$fieldValueProvider = $this->container->get(TaskFieldValueProviderInterface::class);
		assert($fieldValueProvider instanceof TaskFieldValueProviderInterface);
		$fieldValueProvider->persistForTask($this->findTask($taskId), [$field->id => 'prod']);

		$base = '/api/tasks/' . $taskId;
		self::assertSame(
			201,
			$this->request('POST', $base . '/checklist', body: ['text' => 'Step one'], authenticatedAs: $owner)->getStatusCode(),
		);
		self::assertSame(200, $this->request('POST', $base . '/watch', authenticatedAs: $owner)->getStatusCode());
		$subtask = $this->request('POST', $base . '/subtasks', body: ['name' => 'Child'], authenticatedAs: $owner);
		self::assertSame(201, $subtask->getStatusCode());
		$childId = self::intField($this->jsonBody($subtask)['taskId']);
		$recurrence = $this->request(
			'PUT',
			$base . '/recurrence',
			body: ['cadence' => 'Daily', 'interval' => 1, 'endType' => 'Never'],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $recurrence->getStatusCode());

		$dependentTables = [
			'task_field_values' => 'task_id',
			'task_tags' => 'task_id',
			'task_checklist_items' => 'task_id',
			'task_watchers' => 'task_id',
			'task_recurrences' => 'task_id',
			'task_relations' => 'source_task_id',
		];
		foreach ($dependentTables as $table => $column) {
			self::assertSame(1, $this->countRows($table, $column, $taskId), $table . ' fixture row missing');
		}

		self::assertSame(200, $this->request('DELETE', $base, authenticatedAs: $owner)->getStatusCode());

		self::assertSame(0, $this->countRows('tasks', 'id', $taskId));
		foreach ($dependentTables as $table => $column) {
			self::assertSame(0, $this->countRows($table, $column, $taskId), $table . ' row survived the delete');
		}
		self::assertSame(0, $this->countRows('task_relations', 'target_task_id', $childId));
		self::assertSame(1, $this->countRows('tasks', 'id', $childId), 'subtask must be orphaned, not deleted');
	}

	private function countRows(string $table, string $column, int $id): int
	{
		$statement = AppHarness::pdo()->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $column . '` = ?');
		if ($statement === false) {
			throw new RuntimeException('Count query failed for ' . $table);
		}
		$statement->execute([$id]);
		return self::intField($statement->fetchColumn());
	}

	private function findTask(int $taskId): Task
	{
		$taskRepository = $this->container->get(TaskRepository::class);
		assert($taskRepository instanceof TaskRepository);
		$task = $taskRepository->findById($taskId);
		assert($task instanceof Task);
		return $task;
	}

	private function firstStatusId(int $projectId): int
	{
		$workflowRepo = $this->container->get(WorkflowRepository::class);
		assert($workflowRepo instanceof WorkflowRepository);
		$workflow = $workflowRepo->findByProject($projectId);
		assert($workflow !== null);

		$statusRepo = $this->container->get(StatusRepository::class);
		assert($statusRepo instanceof StatusRepository);
		foreach ($statusRepo->findByWorkflow($workflow->id) as $status) {
			return $status->id;
		}

		self::fail('Project has no statuses.');
	}
}
