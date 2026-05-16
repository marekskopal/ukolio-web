<?php

declare(strict_types=1);

namespace TaskManager\Dto;

use TaskManager\Model\Entity\Event;

final readonly class EventDto
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public int $id,
        public string $authorName,
        public ?int $taskId,
        public string $type,
        public array $metadata,
        public string $createdAt,
    ) {
    }

    public static function fromEntity(Event $event): self
    {
        /** @var array<string,mixed> $metadata */
        $metadata = json_decode($event->metadata, true) ?? [];

        return new self(
            id: $event->id,
            authorName: $event->author->name,
            taskId: $event->taskId,
            type: $event->type->value,
            metadata: $metadata,
            createdAt: $event->createdAt->format(DATE_ATOM),
        );
    }
}
