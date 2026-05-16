<?php

declare(strict_types=1);

namespace TaskManager\Model\Repository;

use MarekSkopal\ORM\Repository\AbstractRepository;
use TaskManager\Model\Entity\OAuthClient;

/** @extends AbstractRepository<OAuthClient> */
final class OAuthClientRepository extends AbstractRepository
{
	public function findByClientId(string $clientId): ?OAuthClient
	{
		return $this->findOne(['client_id' => $clientId]);
	}
}
