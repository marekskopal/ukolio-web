<?php

declare(strict_types=1);

namespace Ukolio\Service\Email;

use Symfony\Component\Mime\Email;
use Ukolio\Email\EmailVerificationEmail;
use Ukolio\Email\InvitationEmail;
use Ukolio\Email\PasswordResetEmail;
use Ukolio\Model\Entity\Enum\LocaleEnum;
use Ukolio\Service\Translator\TranslatorServiceInterface;

final readonly class EmailFactory
{
	private string $from;

	private string $appUrl;

	public function __construct(private TranslatorServiceInterface $translator)
	{
		$from = (string) getenv('EMAIL_FROM');
		$this->from = $from !== '' ? $from : 'no-reply@ukolio.local';

		$appUrl = rtrim((string) getenv('APP_URL'), '/');
		$this->appUrl = $appUrl !== '' ? $appUrl : 'http://localhost';
	}

	public function createInvitationEmail(
		string $recipientEmail,
		string $workspaceName,
		string $inviterName,
		string $token,
		LocaleEnum $locale,
	): Email {
		$acceptUrl = $this->appUrl . '/app/invitations/accept?token=' . urlencode($token);

		$subject = strtr(
			$this->translator->translate('email.subject.invitation', $locale),
			['{workspace}' => $workspaceName],
		);

		$html = InvitationEmail::getHtml(
			inviterName: $inviterName,
			workspaceName: $workspaceName,
			acceptUrl: $acceptUrl,
			t: $this->translator->section('email.invitation', $locale),
		);

		return new Email()
			->from($this->from)
			->to($recipientEmail)
			->subject($subject)
			->html($html);
	}

	public function createPasswordResetEmail(string $recipientEmail, string $userName, string $token, LocaleEnum $locale,): Email
	{
		$resetUrl = $this->appUrl . '/app/reset-password?token=' . urlencode($token);

		$subject = $this->translator->translate('email.subject.passwordReset', $locale);

		$html = PasswordResetEmail::getHtml(
			userName: $userName,
			resetUrl: $resetUrl,
			t: $this->translator->section('email.passwordReset', $locale),
		);

		return new Email()
			->from($this->from)
			->to($recipientEmail)
			->subject($subject)
			->html($html);
	}

	public function createEmailVerificationEmail(string $recipientEmail, string $userName, string $token, LocaleEnum $locale,): Email
	{
		$verifyUrl = $this->appUrl . '/app/verify-email?token=' . urlencode($token);

		$subject = $this->translator->translate('email.subject.emailVerification', $locale);

		$html = EmailVerificationEmail::getHtml(
			userName: $userName,
			verifyUrl: $verifyUrl,
			t: $this->translator->section('email.emailVerification', $locale),
		);

		return new Email()
			->from($this->from)
			->to($recipientEmail)
			->subject($subject)
			->html($html);
	}
}
