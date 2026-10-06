<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\Invitation;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;

interface InvitationProviderInterface
{
	/** @return list<Invitation> */
	public function getInvitations(Workspace $workspace): array;

	public function findByToken(string $token): ?Invitation;

	public function createInvitation(User $inviter, Workspace $workspace, string $email, WorkspaceRoleEnum $role): Invitation;

	public function acceptInvitation(User $user, Invitation $invitation): void;

	public function deleteInvitation(Invitation $invitation): void;
}
