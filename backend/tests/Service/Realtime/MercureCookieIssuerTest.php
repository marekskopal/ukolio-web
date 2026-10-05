<?php

declare(strict_types=1);

namespace Ukolio\Tests\Service\Realtime;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Realtime\MercureAccessToken;
use Ukolio\Service\Realtime\MercureCookieIssuer;
use Ukolio\Service\Realtime\RealtimePublisher;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(MercureCookieIssuer::class)]
final class MercureCookieIssuerTest extends IntegrationTestCase
{
	private const string Key = 'integration-test-key-0000000000000000';
	private const string Audience = 'https://hub.example.test/.well-known/mercure';

	public function testCookieListsEveryWorkspaceTheUserBelongsTo(): void
	{
		$owner = Fixture::createUser();
		$workspaceA = Fixture::createWorkspace($owner, 'WS A');
		$workspaceB = Fixture::createWorkspace($owner, 'WS B');

		$other = Fixture::createUser();
		$thirdWorkspace = Fixture::createWorkspace($other, 'WS C');
		Fixture::addMember($thirdWorkspace, $owner, WorkspaceRoleEnum::Member);

		$workspaceProvider = $this->container->get(WorkspaceProviderInterface::class);
		assert($workspaceProvider instanceof WorkspaceProviderInterface);

		$issuer = new MercureCookieIssuer($workspaceProvider, self::Key, self::Audience);

		$cookie = $issuer->issue($owner, secure: false);

		self::assertStringStartsWith('mercureAuthorization=', $cookie);
		self::assertStringContainsString('; Path=/.well-known/mercure', $cookie);
		self::assertStringContainsString('; HttpOnly', $cookie);
		self::assertStringContainsString('; SameSite=Strict', $cookie);
		self::assertStringNotContainsString('; Secure', $cookie);

		$jwt = $this->extractCookieValue($cookie);
		$headers = new stdClass();
		$decoded = JWT::decode($jwt, new Key(self::Key, 'HS256'), $headers);

		// Mercure protocol 1.0 (RFC 9068): the hub rejects tokens without these.
		self::assertSame('at+jwt', $headers->typ ?? null);
		self::assertSame(MercureAccessToken::Issuer, $decoded->iss ?? null);
		self::assertSame(self::Audience, $decoded->aud ?? null);
		self::assertSame((string) $owner->id, $decoded->sub ?? null);

		$details = $decoded->authorization_details ?? null;
		self::assertIsArray($details);
		self::assertCount(1, $details);
		$detail = $details[0];
		self::assertInstanceOf(stdClass::class, $detail);
		self::assertSame('https://mercure.rocks/authorization-detail', $detail->type ?? null);
		self::assertSame([MercureAccessToken::ActionSubscribe], $detail->actions ?? null);

		$topics = $detail->topics ?? null;
		self::assertIsArray($topics);
		$subscribed = array_map(static function (mixed $topic): string {
			self::assertInstanceOf(stdClass::class, $topic);
			self::assertIsString($topic->match ?? null);
			return $topic->match;
		}, array_values($topics));
		sort($subscribed);

		$expected = [
			RealtimePublisher::UserTopicPrefix . $owner->id,
			RealtimePublisher::TopicPrefix . $workspaceA->id,
			RealtimePublisher::TopicPrefix . $workspaceB->id,
			RealtimePublisher::TopicPrefix . $thirdWorkspace->id,
		];
		sort($expected);

		self::assertSame($expected, $subscribed);
	}

	public function testClearCookieExpiresInPast(): void
	{
		$workspaceProvider = $this->container->get(WorkspaceProviderInterface::class);
		assert($workspaceProvider instanceof WorkspaceProviderInterface);

		$issuer = new MercureCookieIssuer($workspaceProvider, self::Key, self::Audience);

		$cookie = $issuer->clear(secure: true);

		self::assertStringStartsWith('mercureAuthorization=;', $cookie);
		self::assertStringContainsString('; Max-Age=0', $cookie);
		self::assertStringContainsString('; Expires=Thu, 01 Jan 1970 00:00:00 GMT', $cookie);
		self::assertStringContainsString('; Secure', $cookie);
	}

	private function extractCookieValue(string $cookieHeader): string
	{
		$first = explode(';', $cookieHeader, 2)[0];
		return substr($first, strlen('mercureAuthorization='));
	}
}
