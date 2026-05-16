<?php

declare(strict_types=1);

namespace TaskManager\Service\Authentication;

use TaskManager\Dto\AuthenticationDto;
use TaskManager\Dto\CredentialsDto;
use TaskManager\Model\Entity\User;

interface AuthenticationServiceInterface
{
	public const string TokenAlgorithm = 'HS256';

	public function authenticate(CredentialsDto $credentials): AuthenticationDto;

	public function createAuthentication(User $user): AuthenticationDto;
}
