<?php

declare(strict_types=1);

namespace Ukolio\Tests\Service\Provider\Fake;

use MarekSkopal\ORM\Query\QueryProvider;
use MarekSkopal\ORM\Schema\Provider\SchemaProvider;
use MarekSkopal\ORM\UnitOfWork\UnitOfWork;
use ReflectionClass;
use Ukolio\Model\Entity\Enum\TaskRelationTypeEnum;
use Ukolio\Model\Entity\TaskRelation;
use Ukolio\Model\Repository\TaskRelationRepository;

final class FakeTaskRelationRepository extends TaskRelationRepository
{
	/** @var list<TaskRelation> */
	public array $stored = [];

	public function __construct()
	{
		/** @var QueryProvider $queryProvider */
		$queryProvider = (new ReflectionClass(QueryProvider::class))->newInstanceWithoutConstructor();

		/** @var SchemaProvider $schemaProvider */
		$schemaProvider = (new ReflectionClass(SchemaProvider::class))->newInstanceWithoutConstructor();

		/** @var UnitOfWork $unitOfWork */
		$unitOfWork = (new ReflectionClass(UnitOfWork::class))->newInstanceWithoutConstructor();

		parent::__construct(TaskRelation::class, $queryProvider, $schemaProvider, $unitOfWork);
	}

	public function findOneById(int $id): ?TaskRelation
	{
		foreach ($this->stored as $rel) {
			if ($rel->id === $id) {
				return $rel;
			}
		}
		return null;
	}

	/** @return list<TaskRelation> */
	public function findOutgoing(int $taskId): array
	{
		$out = [];
		foreach ($this->stored as $rel) {
			if ($rel->sourceTask->id === $taskId) {
				$out[] = $rel;
			}
		}
		return $out;
	}

	/** @return list<TaskRelation> */
	public function findIncoming(int $taskId): array
	{
		$out = [];
		foreach ($this->stored as $rel) {
			if ($rel->targetTask->id === $taskId) {
				$out[] = $rel;
			}
		}
		return $out;
	}

	public function findPair(int $sourceTaskId, int $targetTaskId, TaskRelationTypeEnum $type): ?TaskRelation
	{
		foreach ($this->stored as $rel) {
			if ($rel->sourceTask->id === $sourceTaskId
				&& $rel->targetTask->id === $targetTaskId
				&& $rel->type === $type
			) {
				return $rel;
			}
		}
		return null;
	}

	/** @return list<TaskRelation> */
	public function findOutgoingByType(int $taskId, TaskRelationTypeEnum $type): array
	{
		$out = [];
		foreach ($this->stored as $rel) {
			if ($rel->sourceTask->id === $taskId && $rel->type === $type) {
				$out[] = $rel;
			}
		}
		return $out;
	}

	public function persist(object $entity): void
	{
		if (!isset($entity->id)) {
			$entity->id = count($this->stored) + 1;
			$this->stored[] = $entity;
			return;
		}
		foreach ($this->stored as $i => $existing) {
			if ($existing->id === $entity->id) {
				$this->stored[$i] = $entity;
				return;
			}
		}
		$this->stored[] = $entity;
	}

	public function delete(object $entity): void
	{
		$this->stored = array_values(array_filter(
			$this->stored,
			static fn (TaskRelation $r): bool => $r->id !== $entity->id,
		));
	}

	public function schedulePersist(object $entity): void
	{
		$this->persist($entity);
	}

	public function scheduleDelete(object $entity): void
	{
		$this->delete($entity);
	}

	public function flush(): void
	{
		// persist() and delete() above already write immediately; nothing is ever queued.
	}
}
