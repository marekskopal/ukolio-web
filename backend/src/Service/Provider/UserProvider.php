<?php

declare(strict_types=1);

namespace TaskManager\Service\Provider;

use DateTimeImmutable;
use SensitiveParameter;
use TaskManager\Model\Entity\User;
use TaskManager\Model\Repository\UserRepository;
use const PASSWORD_BCRYPT;

final readonly class UserProvider implements UserProviderInterface
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function getUser(int $userId): ?User
    {
        return $this->userRepository->findUserById($userId);
    }

    public function getUserByEmail(string $email): ?User
    {
        return $this->userRepository->findUserByEmail($email);
    }

    public function createUser(
        #[SensitiveParameter] string $email,
        #[SensitiveParameter] string $password,
        string $name,
    ): User {
        $now = new DateTimeImmutable();
        $user = new User(
            email: $email,
            password: password_hash($password, PASSWORD_BCRYPT),
            name: $name,
        );
        $user->createdAt = $now;
        $user->updatedAt = $now;

        $this->userRepository->persist($user);

        return $user;
    }
}
