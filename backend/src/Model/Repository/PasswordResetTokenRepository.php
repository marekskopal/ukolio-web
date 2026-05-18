<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\PasswordResetToken;

/** @extends AbstractRepository<PasswordResetToken> */
final class PasswordResetTokenRepository extends AbstractRepository
{
	public function findByTokenHash(string $tokenHash): ?PasswordResetToken
	{
		return $this->findOne(['token_hash' => $tokenHash]);
	}
}
