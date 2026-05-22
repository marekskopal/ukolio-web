<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Migrations\Migration\Migration;

final class AddLoginRateLimitToUserMigration extends Migration
{
	public function up(): void
	{
		$pdo = $this->databaseProvider->getDatabase()->getPdo();

		$pdo->exec('ALTER TABLE users ADD COLUMN failed_login_attempts INT NOT NULL DEFAULT 0, ADD COLUMN locked_until DATETIME NULL');
	}

	public function down(): void
	{
		$pdo = $this->databaseProvider->getDatabase()->getPdo();

		$pdo->exec('ALTER TABLE users DROP COLUMN failed_login_attempts, DROP COLUMN locked_until');
	}
}
