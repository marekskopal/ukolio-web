<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use RuntimeException;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskComment;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\TaskCommentRepository;
use Ukolio\Service\Actor\ActorContextInterface;
use Ukolio\Service\Search\SearchIndexer;

final readonly class TaskCommentProvider implements TaskCommentProviderInterface
{
	private const int MaxBodyLength = 10000;

	public function __construct(
		private TaskCommentRepository $taskCommentRepository,
		private EventProviderInterface $eventProvider,
		private ActorContextInterface $actorContext,
		private SearchIndexer $searchIndexer,
	) {
	}

	/** @return list<TaskComment> */
	public function findByTask(Task $task): array
	{
		$result = [];
		foreach ($this->taskCommentRepository->findByTask($task->id) as $comment) {
			$result[] = $comment;
		}
		return $result;
	}

	public function getComment(int $commentId): ?TaskComment
	{
		return $this->taskCommentRepository->findOneById($commentId);
	}

	public function createComment(User $author, Task $task, string $body): TaskComment
	{
		$trimmed = trim($body);
		if ($trimmed === '') {
			throw new RuntimeException('Comment body is empty.');
		}
		if (mb_strlen($trimmed) > self::MaxBodyLength) {
			throw new RuntimeException(sprintf('Comment is too long (max %d characters).', self::MaxBodyLength));
		}

		$now = new DateTimeImmutable();
		$comment = new TaskComment(
			task: $task,
			author: $author,
			body: $trimmed,
			actorType: $this->actorContext->getActorType(),
			mcpClientId: $this->actorContext->getMcpClientId(),
			mcpClientName: $this->actorContext->getMcpClientName(),
		);
		$comment->createdAt = $now;
		$comment->updatedAt = $now;

		$this->taskCommentRepository->persist($comment);

		$this->eventProvider->recordEvent(
			$author,
			$task->project,
			EventTypeEnum::TaskCommentAdded,
			['commentId' => $comment->id, 'taskId' => $task->id],
			$task->id,
		);

		$this->searchIndexer->queueUpsert($task->id);

		return $comment;
	}

	public function deleteComment(User $author, TaskComment $comment): void
	{
		$task = $comment->task;
		$this->taskCommentRepository->delete($comment);

		$this->eventProvider->recordEvent(
			$author,
			$task->project,
			EventTypeEnum::TaskCommentDeleted,
			['commentId' => $comment->id, 'taskId' => $task->id],
			$task->id,
		);

		$this->searchIndexer->queueUpsert($task->id);
	}
}
