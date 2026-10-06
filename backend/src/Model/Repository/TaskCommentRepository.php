<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\TaskComment;

/** @extends ARepository<TaskComment> */
final class TaskCommentRepository extends ARepository
{
	/** @return list<TaskComment> */
	public function findByTask(int $taskId): array
	{
		return $this->select()
			->where(['task_id' => $taskId])
			->orderBy('created_at', 'ASC')
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	public function findOneById(int $id): ?TaskComment
	{
		return $this->findOne(['id' => $id]);
	}

	/** @return list<TaskComment> */
	public function findReplies(int $parentCommentId): array
	{
		return $this->select()
			->where(['parent_comment_id' => $parentCommentId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	/** @return list<TaskComment> */
	public function findByAuthor(int $userId): array
	{
		return $this->select()
			->where(['author_id' => $userId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
