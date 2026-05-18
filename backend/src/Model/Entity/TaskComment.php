<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Ukolio\Model\Entity\Enum\ActorTypeEnum;
use Ukolio\Model\Repository\TaskCommentRepository;

#[Entity(repositoryClass: TaskCommentRepository::class)]
class TaskComment extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Task::class)]
		public readonly Task $task,
		#[ManyToOne(entityClass: User::class, name: 'author_id')]
		public readonly User $author,
		#[Column(type: Type::Text)]
		public readonly string $body,
		#[ColumnEnum(enum: ActorTypeEnum::class, default: ActorTypeEnum::Human)]
		public readonly ActorTypeEnum $actorType = ActorTypeEnum::Human,
		#[Column(type: Type::String, size: 128, nullable: true)]
		public readonly ?string $mcpClientId = null,
		#[Column(type: Type::String, nullable: true)]
		public readonly ?string $mcpClientName = null,
	) {
	}
}
