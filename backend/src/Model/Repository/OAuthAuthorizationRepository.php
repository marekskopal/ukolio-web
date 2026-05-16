<?php

declare(strict_types=1);

namespace TaskManager\Model\Repository;

use MarekSkopal\ORM\Repository\AbstractRepository;
use TaskManager\Model\Entity\OAuthAuthorization;

/** @extends AbstractRepository<OAuthAuthorization> */
final class OAuthAuthorizationRepository extends AbstractRepository
{
	public function findByAuthorizationCodeHash(string $hash): ?OAuthAuthorization
	{
		return $this->findOne(['authorization_code_hash' => $hash]);
	}

	public function findByAccessTokenHash(string $hash): ?OAuthAuthorization
	{
		return $this->findOne(['access_token_hash' => $hash]);
	}

	public function findByRefreshTokenHash(string $hash): ?OAuthAuthorization
	{
		return $this->findOne(['refresh_token_hash' => $hash]);
	}
}
