<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\ScriptRun;

/** @extends ARepository<ScriptRun> */
final class ScriptRunRepository extends ARepository
{
	/** @return list<ScriptRun> */
	public function findByScript(int $scriptId, int $limit, int $offset): array
	{
		return $this->select()
			->where(['script_id' => $scriptId])
			->orderBy('id', 'DESC')
			->limit($limit)
			->offset($offset)
			->fetchAll();
	}

	public function countByScript(int $scriptId): int
	{
		return $this->select()->where(['script_id' => $scriptId])->count();
	}

	public function findLatestByScript(int $scriptId): ?ScriptRun
	{
		return $this->select()
			->where(['script_id' => $scriptId])
			->orderBy('id', 'DESC')
			->limit(1)
			->fetchOne();
	}
}
