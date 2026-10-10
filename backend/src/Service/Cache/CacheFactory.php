<?php

declare(strict_types=1);

namespace Ukolio\Service\Cache;

use Nette\Bridges\Psr\PsrCacheAdapter;
use Nette\Caching\Storages\MemcachedStorage;
use Psr\SimpleCache\CacheInterface;

final class CacheFactory
{
	public static function createPsrCache(string $namespace): CacheInterface
	{
		return new PsrCacheAdapter(new MemcachedStorage(
			host: (string) getenv('MEMCACHED_HOST'),
			port: (int) getenv('MEMCACHED_PORT'),
			prefix: $namespace . '.',
		));
	}
}
