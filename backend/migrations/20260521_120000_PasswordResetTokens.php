<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class PasswordResetTokensMigration extends Migration
{
	public function up(): void
	{
		$this->table('password_reset_tokens')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('user_id', Type::Int, size: 11)
			->addColumn('token_hash', Type::String, size: 64)
			->addColumn('expires_at', Type::Timestamp)
			->addColumn('used_at', Type::Timestamp, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['user_id'], 'password_reset_tokens_user_id_index', false)
			->addIndex(['token_hash'], 'password_reset_tokens_token_hash_unique', true)
			->addForeignKey('user_id', 'users', 'id', 'password_reset_tokens_user_id_fk')
			->create();
	}

	public function down(): void
	{
		$this->table('password_reset_tokens')->drop();
	}
}
