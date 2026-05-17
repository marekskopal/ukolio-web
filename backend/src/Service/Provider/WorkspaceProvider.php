<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Entity\WorkspaceUser;
use Ukolio\Model\Repository\UserRepository;
use Ukolio\Model\Repository\WorkspaceRepository;
use Ukolio\Model\Repository\WorkspaceUserRepository;

final readonly class WorkspaceProvider implements WorkspaceProviderInterface
{
	public function __construct(
		private WorkspaceRepository $workspaceRepository,
		private WorkspaceUserRepository $workspaceUserRepository,
		private UserRepository $userRepository,
	) {
	}

	public function getWorkspace(int $workspaceId): ?Workspace
	{
		return $this->workspaceRepository->findWorkspaceById($workspaceId);
	}

	/** @return Iterator<WorkspaceUser> */
	public function getMemberships(User $user): Iterator
	{
		return $this->workspaceUserRepository->findByUser($user->id);
	}

	/** @return Iterator<WorkspaceUser> */
	public function getMembers(Workspace $workspace): Iterator
	{
		return $this->workspaceUserRepository->findByWorkspace($workspace->id);
	}

	public function findMembership(User $user, Workspace $workspace): ?WorkspaceUser
	{
		return $this->workspaceUserRepository->findMembership($user->id, $workspace->id);
	}

	public function isMember(User $user, Workspace $workspace): bool
	{
		return $this->findMembership($user, $workspace) !== null;
	}

	public function createWorkspace(User $owner, string $name): Workspace
	{
		$now = new DateTimeImmutable();
		$workspace = new Workspace(owner: $owner, name: $name);
		$workspace->createdAt = $now;
		$workspace->updatedAt = $now;

		$this->workspaceRepository->persist($workspace);

		$this->addMember($workspace, $owner, WorkspaceRoleEnum::Owner);

		if ($owner->currentWorkspaceId === null) {
			$this->switchCurrentWorkspace($owner, $workspace);
		}

		return $workspace;
	}

	public function updateWorkspace(Workspace $workspace, string $name): Workspace
	{
		$workspace->name = $name;
		$workspace->updatedAt = new DateTimeImmutable();
		$this->workspaceRepository->persist($workspace);

		return $workspace;
	}

	public function deleteWorkspace(Workspace $workspace): void
	{
		$this->workspaceRepository->delete($workspace);
	}

	public function addMember(Workspace $workspace, User $user, WorkspaceRoleEnum $role): WorkspaceUser
	{
		$existing = $this->workspaceUserRepository->findMembership($user->id, $workspace->id);
		if ($existing !== null) {
			return $existing;
		}

		$now = new DateTimeImmutable();
		$membership = new WorkspaceUser(workspace: $workspace, user: $user, role: $role);
		$membership->createdAt = $now;
		$membership->updatedAt = $now;

		$this->workspaceUserRepository->persist($membership);

		return $membership;
	}

	public function removeMember(WorkspaceUser $membership): void
	{
		$this->workspaceUserRepository->delete($membership);
	}

	public function switchCurrentWorkspace(User $user, Workspace $workspace): void
	{
		$user->currentWorkspaceId = $workspace->id;
		$user->updatedAt = new DateTimeImmutable();
		$this->userRepository->persist($user);
	}

	public function getCurrentWorkspace(User $user): ?Workspace
	{
		if ($user->currentWorkspaceId !== null) {
			$workspace = $this->workspaceRepository->findWorkspaceById($user->currentWorkspaceId);
			if ($workspace !== null && $this->isMember($user, $workspace)) {
				return $workspace;
			}
		}

		foreach ($this->getMemberships($user) as $membership) {
			return $membership->workspace;
		}

		return null;
	}
}
