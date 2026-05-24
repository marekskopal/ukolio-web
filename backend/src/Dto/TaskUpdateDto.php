<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use DateTimeImmutable;
use Ukolio\Model\Entity\Enum\TaskPriorityEnum;

/** @implements ArrayFactoryInterface<array{statusId: int, name: string, description?: ?string, priority?: string, dueDate?: ?string, assigneeId?: ?int, fieldValues?: ?list<array{fieldId: int, value: ?string}>, tagIds?: ?list<int>}> */
final readonly class TaskUpdateDto implements ArrayFactoryInterface
{
	/**
	 * @param array<int, ?string>|null $fieldValues
	 * @param list<int>|null $tagIds
	 */
	public function __construct(
		public int $statusId,
		public string $name,
		public ?string $description,
		public TaskPriorityEnum $priority,
		public ?DateTimeImmutable $dueDate,
		public ?int $assigneeId,
		public bool $assigneeIdProvided,
		public ?array $fieldValues,
		public ?array $tagIds,
	) {
	}

	public static function fromArray(array $data): static
	{
		$dueDate = isset($data['dueDate']) && $data['dueDate'] !== ''
			? new DateTimeImmutable($data['dueDate'])
			: null;

		$assigneeIdProvided = array_key_exists('assigneeId', $data);
		$assigneeId = $assigneeIdProvided ? $data['assigneeId'] : null;

		return new self(
			statusId: $data['statusId'],
			name: $data['name'],
			description: $data['description'] ?? null,
			priority: TaskPriorityEnum::tryFrom($data['priority'] ?? '') ?? TaskPriorityEnum::Medium,
			dueDate: $dueDate,
			assigneeId: $assigneeId,
			assigneeIdProvided: $assigneeIdProvided,
			fieldValues: self::parseFieldValues($data['fieldValues'] ?? null),
			tagIds: self::parseTagIds($data['tagIds'] ?? null),
		);
	}

	/**
	 * @param list<array{fieldId: int, value: ?string}>|null $raw
	 * @return array<int, ?string>|null
	 */
	private static function parseFieldValues(?array $raw): ?array
	{
		if ($raw === null) {
			return null;
		}
		$result = [];
		foreach ($raw as $entry) {
			$result[$entry['fieldId']] = $entry['value'];
		}
		return $result;
	}

	/**
	 * @param list<int>|null $raw
	 * @return list<int>|null
	 */
	private static function parseTagIds(?array $raw): ?array
	{
		if ($raw === null) {
			return null;
		}
		return array_values(array_unique(array_map('intval', $raw)));
	}
}
