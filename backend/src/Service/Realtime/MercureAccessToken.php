<?php

declare(strict_types=1);

namespace Ukolio\Service\Realtime;

use Firebase\JWT\JWT;

/**
 * Mints Mercure protocol 1.0 access tokens: an RFC 9068 JWT (`typ: at+jwt`) whose grants ride in an
 * RFC 9396 `authorization_details` claim. The hub (backend/Caddyfile, `issuer` block) only accepts
 * tokens whose `iss` matches {@see self::Issuer} and whose `aud` equals its pinned resource identifier.
 */
final readonly class MercureAccessToken
{
	public const string Issuer = 'ukolio';
	public const string ActionPublish = 'publish';
	public const string ActionSubscribe = 'subscribe';

	private const string ClientId = 'ukolio-backend';
	private const string Algorithm = 'HS256';
	private const string AuthorizationDetailType = 'https://mercure.rocks/authorization-detail';

	/**
	 * Resolve the hub's resource identifier (the `aud` every token must carry). Mirrors the derivation
	 * in docker/docker-entrypoint.sh, which hands the same value to the hub.
	 */
	public static function resourceIdentifier(): string
	{
		$explicit = (string) getenv('MERCURE_RESOURCE_IDENTIFIER');
		if ($explicit !== '') {
			return $explicit;
		}

		$publishUrl = (string) getenv('MERCURE_PUBLISH_URL');
		return $publishUrl !== '' ? $publishUrl : 'http://localhost/.well-known/mercure';
	}

	/** @param list<string> $topics exact topics, or ["*"] for every topic */
	public static function encode(string $key, string $audience, string $subject, string $action, array $topics, int $ttlSeconds): string
	{
		$now = time();

		return JWT::encode(
			[
				'iss' => self::Issuer,
				'aud' => $audience,
				'sub' => $subject,
				'client_id' => self::ClientId,
				'iat' => $now,
				'exp' => $now + $ttlSeconds,
				'jti' => bin2hex(random_bytes(16)),
				'authorization_details' => [
					[
						'type' => self::AuthorizationDetailType,
						'actions' => [$action],
						'topics' => array_map(static fn (string $topic): array => ['match' => $topic], $topics),
					],
				],
			],
			$key,
			self::Algorithm,
			head: ['typ' => 'at+jwt'],
		);
	}
}
