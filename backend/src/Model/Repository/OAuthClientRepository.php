<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\OAuthClient;

/** @extends ARepository<OAuthClient> */
final class OAuthClientRepository extends ARepository
{
	public function findByClientId(string $clientId): ?OAuthClient
	{
		return $this->findOne(['client_id' => $clientId]);
	}

	/** @return list<OAuthClient> */
	public function findByUser(int $userId): array
	{
		return $this->select()
			->where(['user_id' => $userId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
