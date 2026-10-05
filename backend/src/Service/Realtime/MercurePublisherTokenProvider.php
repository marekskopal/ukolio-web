<?php

declare(strict_types=1);

namespace Ukolio\Service\Realtime;

use Symfony\Component\Mercure\Jwt\TokenProviderInterface;

/**
 * Builds a short-lived publisher JWT on each Hub publish call.
 *
 * The token is signed with MERCURE_PUBLISHER_JWT_KEY and grants `publish` on every topic ("*") so the
 * backend may publish to every topic it emits (workspace-scoped topics, see RealtimePublisher::TopicPrefix).
 */
final readonly class MercurePublisherTokenProvider implements TokenProviderInterface
{
	private const string Subject = 'ukolio-backend';
	private const int TtlSeconds = 60;

	public function __construct(private string $key, private string $audience)
	{
	}

	public function getJwt(): string
	{
		return MercureAccessToken::encode(
			$this->key,
			$this->audience,
			self::Subject,
			MercureAccessToken::ActionPublish,
			['*'],
			self::TtlSeconds,
		);
	}
}
