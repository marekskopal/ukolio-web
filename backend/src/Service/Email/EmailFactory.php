<?php

declare(strict_types=1);

namespace Ukolio\Service\Email;

use Symfony\Component\Mime\Email;
use Ukolio\Email\InvitationEmail;
use Ukolio\Model\Entity\Enum\LocaleEnum;
use Ukolio\Model\Entity\Invitation;
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

	public function createInvitationEmail(Invitation $invitation, string $token, LocaleEnum $locale): Email
	{
		$acceptUrl = $this->appUrl . '/invitations/accept?token=' . urlencode($token);

		$subject = strtr(
			$this->translator->translate('email.subject.invitation', $locale),
			['{workspace}' => $invitation->workspace->name],
		);

		$html = InvitationEmail::getHtml(
			inviterName: $invitation->inviter->name,
			workspaceName: $invitation->workspace->name,
			acceptUrl: $acceptUrl,
			t: $this->translator->section('email.invitation', $locale),
		);

		return new Email()
			->from($this->from)
			->to($invitation->email)
			->subject($subject)
			->html($html);
	}
}
