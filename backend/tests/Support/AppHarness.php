<?php

declare(strict_types=1);

namespace Ukolio\Tests\Support;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\ORM;
use PDO;
use Psr\Container\ContainerInterface;
use Ukolio\App\Application;
use Ukolio\App\ApplicationFactory;

/**
 * Shared per-suite singleton: builds the Application once, exposes the
 * container for tests, and resets database + identity map between tests.
 */
final class AppHarness
{
	private static ?Application $application = null;

	/** @var list<string>|null */
	private static ?array $tables = null;

	public static function initialize(): void
	{
		if (self::$application !== null) {
			return;
		}
		self::$application = ApplicationFactory::create();
	}

	public static function app(): Application
	{
		if (self::$application === null) {
			throw new \LogicException('AppHarness not initialized; tests bootstrap missed.');
		}
		return self::$application;
	}

	public static function container(): ContainerInterface
	{
		return self::app()->container;
	}

	public static function pdo(): PDO
	{
		$db = self::app()->dbContext->getDatabase();
		assert($db instanceof DatabaseInterface);
		return $db->getPdo();
	}

	/**
	 * Wipe all tables (except migrations) and clear the ORM identity map so
	 * the next test starts from a clean slate.
	 */
	public static function resetState(): void
	{
		$pdo = self::pdo();

		if (self::$tables === null) {
			$rows = $pdo->query('SHOW TABLES')?->fetchAll(PDO::FETCH_COLUMN) ?: [];
			self::$tables = array_values(array_filter(
				array_map(static fn (mixed $r): string => (string) $r, $rows),
				static fn (string $name): bool => $name !== 'migrations',
			));
		}

		$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
		foreach (self::$tables as $table) {
			$pdo->exec('TRUNCATE TABLE `' . $table . '`');
		}
		$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

		$orm = self::container()->get(ORM::class);
		assert($orm instanceof ORM);
		$orm->getEntityCache()->clear();
	}
}
