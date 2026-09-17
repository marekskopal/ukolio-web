<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

/**
 * `last_login_at` records the moment a user last established a session by proving their identity
 * (password or Google). Refresh-token exchanges deliberately do not touch it — they extend an
 * existing session rather than starting one — so the column stays a usable "is this account still
 * in use?" signal for the admin user list. Null means the user has never signed in since the
 * column was introduced.
 */
final class AddLastLoginAtToUsersMigration extends Migration
{
	public function up(): void
	{
		$this->table('users')
			->addColumn('last_login_at', Type::Timestamp, nullable: true)
			->alter();
	}

	public function down(): void
	{
		$this->table('users')
			->dropColumn('last_login_at')
			->alter();
	}
}
