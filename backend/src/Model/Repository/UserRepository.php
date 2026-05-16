<?php

declare(strict_types=1);

namespace TaskManager\Model\Repository;

use MarekSkopal\ORM\Repository\AbstractRepository;
use TaskManager\Model\Entity\User;

/** @extends AbstractRepository<User> */
final class UserRepository extends AbstractRepository
{
    public function findUserById(int $userId): ?User
    {
        return $this->findOne(['id' => $userId]);
    }

    public function findUserByEmail(string $email): ?User
    {
        return $this->findOne(['email' => $email]);
    }
}
