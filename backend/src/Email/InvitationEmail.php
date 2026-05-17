<?php

declare(strict_types=1);

namespace Ukolio\Email;

use const ENT_QUOTES;

final readonly class InvitationEmail
{
	public static function getHtml(string $inviterName, string $workspaceName, string $acceptUrl): string
	{
		$inviter = htmlspecialchars($inviterName, ENT_QUOTES, 'UTF-8');
		$workspace = htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8');
		$url = htmlspecialchars($acceptUrl, ENT_QUOTES, 'UTF-8');

		$fontStyle = 'font-family: Inter, Helvetica, Arial, sans-serif; font-size: 16px; line-height: 1.5; color: #1f2937;';
		$buttonStyle = 'display: inline-block; padding: 12px 24px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 8px;';

		return <<<HTML
<html>
<body style="{$fontStyle} background-color:#f3f4f6; padding: 32px;">
	<div style="max-width: 560px; margin: 0 auto; padding: 32px; background-color: #ffffff; border-radius: 12px;">
		<h1 style="margin-top: 0;">You've been invited to Ukolio</h1>
		<p>{$inviter} has invited you to join the workspace <strong>{$workspace}</strong>.</p>
		<p>Click the button below to accept the invitation. If you don't have an account yet, you'll be asked to sign up first using this email address.</p>
		<p style="text-align: center; margin: 32px 0;">
			<a href="{$url}" style="{$buttonStyle}">Accept invitation</a>
		</p>
		<p style="color: #6b7280; font-size: 14px;">Or copy this link into your browser:<br><a href="{$url}">{$url}</a></p>
		<p style="color: #6b7280; font-size: 14px;">This invitation expires in 7 days.</p>
	</div>
</body>
</html>
HTML;
	}
}
