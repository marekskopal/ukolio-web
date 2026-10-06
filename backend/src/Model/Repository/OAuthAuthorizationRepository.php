<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\OAuthAuthorization;

/** @extends ARepository<OAuthAuthorization> */
final class OAuthAuthorizationRepository extends ARepository
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

	/**
	 * @param list<int> $userIds
	 * @return list<OAuthAuthorization>
	 */
	public function findByUserIds(array $userIds): array
	{
		if ($userIds === []) {
			return [];
		}

		return $this->select()
			->where(['user_id', 'IN', $userIds])
			->orderBy('created_at', 'DESC')
			->fetchAll();
	}
}
