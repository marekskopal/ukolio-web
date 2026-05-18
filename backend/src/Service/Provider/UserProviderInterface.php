<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\Enum\LocaleEnum;
use Ukolio\Model\Entity\User;

interface UserProviderInterface
{
	public function getUser(int $userId): ?User;

	public function getUserByEmail(string $email): ?User;

	public function createUser(string $email, string $password, string $name, LocaleEnum $locale = LocaleEnum::En): User;

	public function updateUser(User $user, ?string $name = null, ?LocaleEnum $locale = null): User;

	public function updateUserPassword(User $user, string $newPassword): User;
}
