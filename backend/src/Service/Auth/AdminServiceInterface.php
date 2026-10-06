<?php

declare(strict_types=1);

namespace Ukolio\Service\Auth;

use Ukolio\Model\Entity\Enum\SystemRoleEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;

interface AdminServiceInterface
{
	/** @return list<User> */
	public function listUsers(): array;

	/** @return list<Workspace> */
	public function listWorkspaces(): array;

	public function countMembers(Workspace $workspace): int;

	public function countProjects(Workspace $workspace): int;

	public function countTasks(Workspace $workspace): int;

	public function countWorkspacesForUser(User $user): int;

	public function countOwnedWorkspaces(User $user): int;

	/** @return list<Workspace> */
	public function findSoleOwnerWorkspaces(User $user): array;

	public function updateUser(User $actor, User $target, ?string $name, ?string $email, ?SystemRoleEnum $systemRole): User;

	public function deleteUser(User $actor, User $target): void;

	public function deleteWorkspace(User $actor, Workspace $workspace): void;
}
