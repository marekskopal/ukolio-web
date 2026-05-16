<?php

declare(strict_types=1);

namespace TaskManager\Dto;

use DateTimeImmutable;
use TaskManager\Model\Entity\Enum\TaskPriorityEnum;

/**
 * @implements ArrayFactoryInterface<array{statusId: int, name: string, description?: ?string, priority?: string, dueDate?: ?string}>
 */
final readonly class TaskCreateDto implements ArrayFactoryInterface
{
    public function __construct(
        public int $statusId,
        public string $name,
        public ?string $description,
        public TaskPriorityEnum $priority,
        public ?DateTimeImmutable $dueDate,
    ) {
    }

    public static function fromArray(array $data): static
    {
        $dueDate = isset($data['dueDate']) && $data['dueDate'] !== null && $data['dueDate'] !== ''
            ? new DateTimeImmutable($data['dueDate'])
            : null;

        return new self(
            statusId: $data['statusId'],
            name: $data['name'],
            description: $data['description'] ?? null,
            priority: TaskPriorityEnum::tryFrom($data['priority'] ?? '') ?? TaskPriorityEnum::Medium,
            dueDate: $dueDate,
        );
    }
}
