<?php

declare(strict_types=1);

namespace Ukolio\Service\Email;

use Symfony\Component\Mime\Email;
use Ukolio\Email\InvitationEmail;
use Ukolio\Model\Entity\Invitation;

final readonly class EmailFactory
{
	private string $from;

	private string $appUrl;

	public function __construct()
	{
		$from = (string) getenv('EMAIL_FROM');
		$this->from = $from !== '' ? $from : 'no-reply@ukolio.local';

		$appUrl = rtrim((string) getenv('APP_URL'), '/');
		$this->appUrl = $appUrl !== '' ? $appUrl : 'http://localhost';
	}

	public function createInvitationEmail(Invitation $invitation, string $token): Email
	{
		$acceptUrl = $this->appUrl . '/invitations/accept?token=' . urlencode($token);

		$html = InvitationEmail::getHtml(
			inviterName: $invitation->inviter->name,
			workspaceName: $invitation->workspace->name,
			acceptUrl: $acceptUrl,
		);

		return new Email()
			->from($this->from)
			->to($invitation->email)
			->subject('You\'ve been invited to ' . $invitation->workspace->name . ' on Ukolio')
			->html($html);
	}
}
