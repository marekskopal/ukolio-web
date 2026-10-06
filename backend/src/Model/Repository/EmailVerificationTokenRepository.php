<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\EmailVerificationToken;

/** @extends ARepository<EmailVerificationToken> */
final class EmailVerificationTokenRepository extends ARepository
{
	public function findByTokenHash(string $tokenHash): ?EmailVerificationToken
	{
		return $this->findOne(['token_hash' => $tokenHash]);
	}
}
