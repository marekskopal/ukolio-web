<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SensitiveParameter;
use Ukolio\Model\Entity\PasswordResetToken;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\PasswordResetTokenRepository;
use Ukolio\Service\Email\EmailFactory;
use Ukolio\Service\Email\MailerFactory;
use const FILTER_VALIDATE_EMAIL;

final readonly class PasswordResetProvider implements PasswordResetProviderInterface
{
	private const string ResetLifetime = '+1 hour';

	public function __construct(
		private PasswordResetTokenRepository $tokenRepository,
		private UserProviderInterface $userProvider,
		private EmailFactory $emailFactory,
		private MailerFactory $mailerFactory,
		private LoggerInterface $logger,
	) {
	}

	public function requestReset(string $email): void
	{
		$email = mb_strtolower(trim($email));
		if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			return;
		}

		$user = $this->userProvider->getUserByEmail($email);
		if ($user === null) {
			return;
		}

		$token = bin2hex(random_bytes(32));

		$now = new DateTimeImmutable();
		$resetToken = new PasswordResetToken(
			user: $user,
			tokenHash: hash('sha256', $token),
			expiresAt: $now->modify(self::ResetLifetime),
		);
		$resetToken->createdAt = $now;
		$resetToken->updatedAt = $now;

		$this->tokenRepository->persist($resetToken);

		try {
			$mailer = $this->mailerFactory->create();
			$mailer->send($this->emailFactory->createPasswordResetEmail($user, $token, $user->locale));
		} catch (\Throwable $e) {
			$this->logger->error('Failed to send password reset email: ' . $e->getMessage());
		}
	}

	public function findByToken(string $token): ?PasswordResetToken
	{
		return $this->tokenRepository->findByTokenHash(hash('sha256', $token));
	}

	public function confirmReset(PasswordResetToken $token, #[SensitiveParameter] string $newPassword): User
	{
		if ($token->usedAt !== null) {
			throw new RuntimeException('This reset link has already been used.');
		}

		if ($token->expiresAt < new DateTimeImmutable()) {
			throw new RuntimeException('This reset link has expired.');
		}

		$user = $this->userProvider->updateUserPassword($token->user, $newPassword);

		$now = new DateTimeImmutable();
		$token->usedAt = $now;
		$token->updatedAt = $now;
		$this->tokenRepository->persist($token);

		return $user;
	}
}
