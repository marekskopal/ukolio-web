<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\Invitation;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\InvitationRepository;
use Ukolio\Service\Email\EmailFactory;
use Ukolio\Service\Email\MailerFactory;
use const FILTER_VALIDATE_EMAIL;

final readonly class InvitationProvider implements InvitationProviderInterface
{
	private const string InvitationLifetime = '+7 days';

	public function __construct(
		private InvitationRepository $invitationRepository,
		private WorkspaceProviderInterface $workspaceProvider,
		private EmailFactory $emailFactory,
		private MailerFactory $mailerFactory,
		private LoggerInterface $logger,
	) {
	}

	/** @return Iterator<Invitation> */
	public function getInvitations(Workspace $workspace): Iterator
	{
		return $this->invitationRepository->findByWorkspace($workspace->id);
	}

	public function findByToken(string $token): ?Invitation
	{
		return $this->invitationRepository->findByTokenHash(hash('sha256', $token));
	}

	public function createInvitation(User $inviter, Workspace $workspace, string $email, WorkspaceRoleEnum $role): Invitation
	{
		$email = mb_strtolower(trim($email));
		if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			throw new RuntimeException('Invalid email address.');
		}

		$token = bin2hex(random_bytes(32));

		$now = new DateTimeImmutable();
		$invitation = new Invitation(
			workspace: $workspace,
			inviter: $inviter,
			email: $email,
			tokenHash: hash('sha256', $token),
			role: $role,
			expiresAt: $now->modify(self::InvitationLifetime),
		);
		$invitation->createdAt = $now;
		$invitation->updatedAt = $now;

		$this->invitationRepository->persist($invitation);

		try {
			$mailer = $this->mailerFactory->create();
			$mailer->send($this->emailFactory->createInvitationEmail($invitation, $token));
		} catch (\Throwable $e) {
			$this->logger->error('Failed to send invitation email: ' . $e->getMessage());
		}

		return $invitation;
	}

	public function acceptInvitation(User $user, Invitation $invitation): void
	{
		if ($invitation->acceptedAt !== null) {
			throw new RuntimeException('Invitation has already been accepted.');
		}

		if ($invitation->expiresAt < new DateTimeImmutable()) {
			throw new RuntimeException('Invitation has expired.');
		}

		if (mb_strtolower($user->email) !== $invitation->email) {
			throw new RuntimeException('This invitation is for a different email address.');
		}

		$this->workspaceProvider->addMember($invitation->workspace, $user, $invitation->role);

		$invitation->acceptedAt = new DateTimeImmutable();
		$invitation->updatedAt = $invitation->acceptedAt;
		$this->invitationRepository->persist($invitation);

		$this->workspaceProvider->switchCurrentWorkspace($user, $invitation->workspace);
	}

	public function deleteInvitation(Invitation $invitation): void
	{
		$this->invitationRepository->delete($invitation);
	}
}
