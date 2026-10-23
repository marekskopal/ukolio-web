<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\Enum\ActorTypeEnum;
use Ukolio\Model\Entity\TaskComment;
use Ukolio\Service\Avatar\AvatarUrl;
use const DATE_ATOM;

final readonly class TaskCommentDto
{
	public function __construct(
		public int $id,
		public int $taskId,
		public int $authorId,
		public string $authorName,
		public ?string $authorAvatarUrl,
		public string $body,
		public bool $createdByAgent,
		public ?string $mcpClientId,
		public ?string $mcpClientName,
		public ?int $parentCommentId,
		public bool $edited,
		public string $createdAt,
	) {
	}

	public static function fromEntity(TaskComment $comment): self
	{
		return new self(
			id: $comment->id,
			taskId: $comment->task->id,
			authorId: $comment->author->id,
			authorName: $comment->author->name,
			authorAvatarUrl: AvatarUrl::forUser($comment->author),
			body: $comment->body,
			createdByAgent: $comment->actorType === ActorTypeEnum::Agent,
			mcpClientId: $comment->mcpClientId,
			mcpClientName: $comment->mcpClientName,
			parentCommentId: $comment->parentCommentId,
			edited: $comment->editedAt !== null,
			createdAt: $comment->createdAt->format(DATE_ATOM),
		);
	}
}
