<?php

declare(strict_types=1);

namespace Ukolio\Service\Realtime;

use Ukolio\Model\Entity\User;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class MercureCookieIssuer implements MercureCookieIssuerInterface
{
	private const string CookieName = 'mercureAuthorization';
	private const string CookiePath = '/.well-known/mercure';
	private const int TtlSeconds = 3600;

	public function __construct(
		private WorkspaceProviderInterface $workspaceProvider,
		private string $subscriberKey,
		private string $audience,
	)
	{
	}

	public function issue(User $user, bool $secure): string
	{
		// The user's own private topic (notification pings, U-83) plus each of their workspace topics.
		$topics = [RealtimePublisher::UserTopicPrefix . $user->id];
		foreach ($this->workspaceProvider->getMemberships($user) as $membership) {
			$topics[] = RealtimePublisher::TopicPrefix . $membership->workspace->id;
		}

		$jwt = MercureAccessToken::encode(
			$this->subscriberKey,
			$this->audience,
			(string) $user->id,
			MercureAccessToken::ActionSubscribe,
			$topics,
			self::TtlSeconds,
		);

		return $this->buildCookieHeader($jwt, self::TtlSeconds, $secure);
	}

	public function clear(bool $secure): string
	{
		return $this->buildCookieHeader('', 0, $secure, expireInPast: true);
	}

	private function buildCookieHeader(string $value, int $maxAge, bool $secure, bool $expireInPast = false): string
	{
		$parts = [
			self::CookieName . '=' . $value,
			'Path=' . self::CookiePath,
			'HttpOnly',
			'SameSite=Strict',
		];
		if ($secure) {
			$parts[] = 'Secure';
		}
		if ($expireInPast) {
			$parts[] = 'Max-Age=0';
			$parts[] = 'Expires=Thu, 01 Jan 1970 00:00:00 GMT';
		} else {
			$parts[] = 'Max-Age=' . $maxAge;
		}
		return implode('; ', $parts);
	}
}
