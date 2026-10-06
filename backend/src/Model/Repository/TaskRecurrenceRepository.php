<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use DateTimeImmutable;
use Ukolio\Model\Entity\TaskRecurrence;

/** @extends ARepository<TaskRecurrence> */
final class TaskRecurrenceRepository extends ARepository
{
	public function findById(int $id): ?TaskRecurrence
	{
		return $this->findOne(['id' => $id]);
	}

	public function findByTask(int $taskId): ?TaskRecurrence
	{
		return $this->findOne(['task_id' => $taskId]);
	}

	/**
	 * Active series whose next occurrence is due at or before `$now` — the daily-tick query.
	 *
	 * @return list<TaskRecurrence>
	 */
	public function findDue(DateTimeImmutable $now): array
	{
		return $this->select()
			->where(['active' => true])
			->where(['next_run_at', '<=', $now->format('Y-m-d H:i:s')])
			->fetchAll();
	}
}
