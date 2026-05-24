<?php

declare(strict_types=1);

namespace Ukolio\Service\Auth;

use DateTimeImmutable;
use Iterator;
use RuntimeException;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Enum\SystemRoleEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\UserRepository;
use Ukolio\Model\Repository\WorkspaceRepository;
use Ukolio\Model\Repository\WorkspaceUserRepository;
use Ukolio\Service\Provider\EventProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class AdminService implements AdminServiceInterface
{
	public function __construct(
		private UserRepository $userRepository,
		private WorkspaceRepository $workspaceRepository,
		private WorkspaceUserRepository $workspaceUserRepository,
		private WorkspaceProviderInterface $workspaceProvider,
		private EventProviderInterface $eventProvider,
	) {
	}

	/** @return Iterator<User> */
	public function listUsers(): Iterator
	{
		return $this->userRepository->findAllUsers();
	}

	/** @return Iterator<Workspace> */
	public function listWorkspaces(): Iterator
	{
		return $this->workspaceRepository->findAllWorkspaces();
	}

	public function countMembers(Workspace $workspace): int
	{
		return iterator_count($this->workspaceUserRepository->findByWorkspace($workspace->id));
	}

	public function countWorkspacesForUser(User $user): int
	{
		return iterator_count($this->workspaceUserRepository->findByUser($user->id));
	}

	public function countOwnedWorkspaces(User $user): int
	{
		return iterator_count($this->workspaceRepository->findByOwner($user->id));
	}

	/** @return list<Workspace> */
	public function findSoleOwnerWorkspaces(User $user): array
	{
		$blocking = [];
		foreach ($this->workspaceRepository->findByOwner($user->id) as $workspace) {
			$blocking[] = $workspace;
		}
		return $blocking;
	}

	public function updateUser(User $actor, User $target, ?string $name, ?string $email, ?SystemRoleEnum $systemRole): User
	{
		if ($name !== null) {
			$trimmed = trim($name);
			if ($trimmed !== '') {
				$target->name = $trimmed;
			}
		}

		if ($email !== null) {
			$trimmed = trim($email);
			if ($trimmed !== '') {
				$target->email = $trimmed;
			}
		}

		if ($systemRole !== null && $systemRole !== $target->systemRole) {
			if (
				$target->systemRole === SystemRoleEnum::SystemAdmin
				&& $systemRole !== SystemRoleEnum::SystemAdmin
				&& $this->userRepository->countSystemAdmins() <= 1
			) {
				throw new RuntimeException('Cannot demote the last system administrator.');
			}

			$previous = $target->systemRole;
			$target->systemRole = $systemRole;

			$this->eventProvider->recordWorkspaceEvent(
				$actor,
				null,
				EventTypeEnum::AdminChangedSystemRole,
				[
					'userId' => $target->id,
					'userEmail' => $target->email,
					'fromRole' => $previous->value,
					'toRole' => $systemRole->value,
				],
			);
		}

		$target->updatedAt = new DateTimeImmutable();
		$this->userRepository->persist($target);

		return $target;
	}

	public function deleteUser(User $actor, User $target): void
	{
		if ($target->id === $actor->id) {
			throw new RuntimeException('You cannot delete your own account through the admin interface.');
		}

		$blocking = $this->findSoleOwnerWorkspaces($target);
		if ($blocking !== []) {
			$names = array_map(static fn (Workspace $w): string => $w->name, $blocking);

			throw new RuntimeException(
				'Cannot delete user — they own these workspaces: ' . implode(', ', $names)
				. '. Transfer ownership or delete the workspaces first.',
			);
		}

		if ($target->systemRole === SystemRoleEnum::SystemAdmin && $this->userRepository->countSystemAdmins() <= 1) {
			throw new RuntimeException('Cannot delete the last system administrator.');
		}

		foreach ($this->workspaceUserRepository->findByUser($target->id) as $membership) {
			$this->workspaceProvider->removeMember($membership);
		}

		$this->eventProvider->recordWorkspaceEvent(
			$actor,
			null,
			EventTypeEnum::AdminDeletedUser,
			[
				'userId' => $target->id,
				'userEmail' => $target->email,
			],
		);

		$this->userRepository->delete($target);
	}

	public function deleteWorkspace(User $actor, Workspace $workspace): void
	{
		$this->eventProvider->recordWorkspaceEvent(
			$actor,
			$workspace,
			EventTypeEnum::AdminDeletedWorkspace,
			[
				'workspaceId' => $workspace->id,
				'workspaceName' => $workspace->name,
				'ownerId' => $workspace->owner->id,
				'ownerEmail' => $workspace->owner->email,
			],
		);

		$this->workspaceProvider->deleteWorkspace($workspace);
	}
}
