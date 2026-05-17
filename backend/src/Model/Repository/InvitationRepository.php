<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\Invitation;

/** @extends AbstractRepository<Invitation> */
final class InvitationRepository extends AbstractRepository
{
	public function findByTokenHash(string $tokenHash): ?Invitation
	{
		return $this->findOne(['token_hash' => $tokenHash]);
	}

	/** @return Iterator<Invitation> */
	public function findByWorkspace(int $workspaceId): Iterator
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('id', 'DESC')
			->fetchAll();
	}
}
