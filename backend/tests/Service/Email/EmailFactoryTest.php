<?php

declare(strict_types=1);

namespace Ukolio\Tests\Service\Email;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ukolio\Model\Entity\Enum\LocaleEnum;
use Ukolio\Model\Entity\Enum\SystemRoleEnum;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\Invitation;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Service\Email\EmailFactory;
use Ukolio\Service\Translator\TranslatorService;

#[CoversClass(EmailFactory::class)]
final class EmailFactoryTest extends TestCase
{
	public function testInvitationEmailContainsTokenAcceptUrlAndIsLocalised(): void
	{
		putenv('APP_URL=https://app.ukolio.example');
		putenv('EMAIL_FROM=no-reply@ukolio.example');

		$translator = new TranslatorService(__DIR__ . '/../../../translations');
		$factory = new EmailFactory($translator);

		$inviter = $this->makeUser('inviter@example.com', 'Inviter', LocaleEnum::Cs);
		$workspace = $this->makeWorkspace($inviter, 'Acme');
		$invitation = $this->makeInvitation($workspace, $inviter, 'invitee@example.com');

		$email = $factory->createInvitationEmail($invitation, 'raw-token-123', LocaleEnum::Cs);

		self::assertSame('no-reply@ukolio.example', $email->getFrom()[0]->getAddress());
		self::assertSame('invitee@example.com', $email->getTo()[0]->getAddress());
		self::assertStringContainsString('Acme', $email->getSubject());

		$html = $email->getHtmlBody();
		self::assertIsString($html);
		self::assertStringContainsString('raw-token-123', $html);
		self::assertStringContainsString('https://app.ukolio.example/app/invitations/accept?token=raw-token-123', $html);
	}

	public function testPasswordResetEmailIncludesUrlAndToken(): void
	{
		putenv('APP_URL=https://app.ukolio.example');
		putenv('EMAIL_FROM=no-reply@ukolio.example');

		$translator = new TranslatorService(__DIR__ . '/../../../translations');
		$factory = new EmailFactory($translator);

		$user = $this->makeUser('reset@example.com', 'Reset', LocaleEnum::En);
		$email = $factory->createPasswordResetEmail($user, 'reset-token', LocaleEnum::En);

		$html = $email->getHtmlBody();
		self::assertIsString($html);
		self::assertStringContainsString('reset-token', $html);
		self::assertStringContainsString('/app/reset-password?token=reset-token', $html);
	}

	public function testEmailVerificationEmailIncludesUrlAndToken(): void
	{
		putenv('APP_URL=https://app.ukolio.example');
		putenv('EMAIL_FROM=no-reply@ukolio.example');

		$translator = new TranslatorService(__DIR__ . '/../../../translations');
		$factory = new EmailFactory($translator);

		$user = $this->makeUser('verify@example.com', 'Verify', LocaleEnum::En);
		$email = $factory->createEmailVerificationEmail($user, 'verify-token', LocaleEnum::En);

		$html = $email->getHtmlBody();
		self::assertIsString($html);
		self::assertStringContainsString('verify-token', $html);
		self::assertStringContainsString('/app/verify-email?token=verify-token', $html);
	}

	private function makeUser(string $email, string $name, LocaleEnum $locale): User
	{
		$user = new User(
			email: $email,
			password: 'x',
			name: $name,
			locale: $locale,
			currentWorkspaceId: null,
			systemRole: SystemRoleEnum::User,
		);
		$user->id = 1;
		$user->createdAt = new DateTimeImmutable();
		$user->updatedAt = new DateTimeImmutable();
		return $user;
	}

	private function makeWorkspace(User $owner, string $name): Workspace
	{
		$ws = new Workspace(owner: $owner, name: $name);
		$ws->id = 1;
		$ws->createdAt = new DateTimeImmutable();
		$ws->updatedAt = new DateTimeImmutable();
		return $ws;
	}

	private function makeInvitation(Workspace $workspace, User $inviter, string $email): Invitation
	{
		$inv = new Invitation(
			workspace: $workspace,
			inviter: $inviter,
			email: $email,
			tokenHash: 'hash',
			role: WorkspaceRoleEnum::Member,
			expiresAt: new DateTimeImmutable('+7 days'),
		);
		$inv->id = 1;
		$inv->createdAt = new DateTimeImmutable();
		$inv->updatedAt = new DateTimeImmutable();
		$inv->acceptedAt = null;
		return $inv;
	}
}
