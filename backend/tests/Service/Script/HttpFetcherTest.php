<?php

declare(strict_types=1);

namespace Ukolio\Tests\Service\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Ukolio\Service\Script\Host\HttpFetcher;

#[CoversClass(HttpFetcher::class)]
final class HttpFetcherTest extends TestCase
{
	/** @param list<string> $allowed */
	private function assertHostAllowed(string $url, array $allowed): void
	{
		$method = new ReflectionMethod(HttpFetcher::class, 'assertHostAllowed');
		$method->invoke(new HttpFetcher(), $url, $allowed);
	}

	public function testEmptyAllowlistAllowsAnyHost(): void
	{
		$this->expectNotToPerformAssertions();
		$this->assertHostAllowed('https://anything.example.com/x', []);
	}

	public function testExactHostMatchIsAllowed(): void
	{
		$this->expectNotToPerformAssertions();
		$this->assertHostAllowed('https://hooks.slack.com/services/x', ['hooks.slack.com']);
	}

	public function testSubdomainOfAllowedHostIsAllowed(): void
	{
		$this->expectNotToPerformAssertions();
		$this->assertHostAllowed('https://api.github.com/repos', ['github.com']);
	}

	public function testHostNotInAllowlistIsRejected(): void
	{
		$this->expectException(RuntimeException::class);
		$this->assertHostAllowed('https://evil.example.com', ['github.com']);
	}

	public function testSuffixLookalikeIsNotTreatedAsSubdomain(): void
	{
		// "notgithub.com" must NOT match the "github.com" pattern.
		$this->expectException(RuntimeException::class);
		$this->assertHostAllowed('https://notgithub.com', ['github.com']);
	}
}
