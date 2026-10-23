<?php

declare(strict_types=1);

namespace Ukolio\Tests\Support;

use RuntimeException;
use Ukolio\Service\Storage\FileStorageInterface;

/** Stands in for S3 in the test suite, which has no object store to talk to. */
final class InMemoryFileStorage implements FileStorageInterface
{
	/** @var array<string, string> */
	private array $objects = [];

	public function put(string $key, string $body, string $contentType): void
	{
		$this->objects[$key] = $body;
	}

	public function get(string $key): string
	{
		return $this->objects[$key] ?? throw new RuntimeException('Failed to read object "' . $key . '".');
	}

	public function delete(string $key): void
	{
		unset($this->objects[$key]);
	}

	public function has(string $key): bool
	{
		return isset($this->objects[$key]);
	}

	public function clear(): void
	{
		$this->objects = [];
	}
}
