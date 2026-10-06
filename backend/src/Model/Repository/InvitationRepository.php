<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use DateTimeImmutable;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\Invitation;

/** @extends AbstractRepository<Invitation> */
final class InvitationRepository extends AbstractRepository
{
	public function findByTokenHash(string $tokenHash): ?Invitation
	{
		return $this->findOne(['token_hash' => $tokenHash]);
	}

	public function countByWorkspaceSince(int $workspaceId, DateTimeImmutable $since): int
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->where(['created_at', '>=', $since->format('Y-m-d H:i:s')])
			->count();
	}

	/** @return list<Invitation> */
	public function findByWorkspace(int $workspaceId): array
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('id', 'DESC')
			->fetchAll();
	}

	/** @return list<Invitation> */
	public function findByInviter(int $userId): array
	{
		return $this->select()
			->where(['inviter_id' => $userId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
