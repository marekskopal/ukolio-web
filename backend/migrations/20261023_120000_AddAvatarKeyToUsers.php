<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

/**
 * `avatar_key` is the random token naming the user's current avatar object in S3
 * (`avatars/{userId}/{avatarKey}.webp`). It doubles as the cache-buster in the public avatar URL:
 * replacing the avatar rotates the key, so the immutable browser cache never serves a stale image.
 * Null means no avatar — the UI falls back to initials.
 */
final class AddAvatarKeyToUsersMigration extends Migration
{
	public function up(): void
	{
		$this->table('users')
			->addColumn('avatar_key', Type::String, size: 32, nullable: true, default: null)
			->alter();
	}

	public function down(): void
	{
		$this->table('users')
			->dropColumn('avatar_key')
			->alter();
	}
}
