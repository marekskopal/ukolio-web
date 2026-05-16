<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class OAuthTablesMigration extends Migration
{
	public function up(): void
	{
		$this->table('oauth_clients')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('client_id', Type::String, size: 128)
			->addColumn('client_name', Type::String)
			->addColumn('redirect_uris', Type::String)
			->addColumn('user_id', Type::Int, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addForeignKey('user_id', 'users', 'id', 'oauth_clients_user_id_users_id_fk')
			->addIndex(['client_id'], 'oauth_clients_client_id_unique', true)
			->create();

		$this->table('oauth_authorizations')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('client_id', Type::String, size: 128)
			->addColumn('user_id', Type::Int)
			->addColumn('authorization_code_hash', Type::String, size: 64, nullable: true)
			->addColumn('code_challenge', Type::String, nullable: true)
			->addColumn('code_challenge_method', Type::String, size: 10, nullable: true)
			->addColumn('redirect_uri', Type::String, nullable: true)
			->addColumn('access_token_hash', Type::String, size: 64, nullable: true)
			->addColumn('refresh_token_hash', Type::String, size: 64, nullable: true)
			->addColumn('access_token_expires', Type::Int, nullable: true)
			->addColumn('refresh_token_expires', Type::Int, nullable: true)
			->addColumn('code_expires', Type::Int, nullable: true)
			->addColumn('revoked', Type::Boolean)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addForeignKey('user_id', 'users', 'id', 'oauth_authorizations_user_id_users_id_fk')
			->addIndex(['authorization_code_hash'], 'oauth_auth_code_hash_idx', false)
			->addIndex(['access_token_hash'], 'oauth_access_token_hash_idx', false)
			->addIndex(['refresh_token_hash'], 'oauth_refresh_token_hash_idx', false)
			->create();
	}

	public function down(): void
	{
		$this->table('oauth_authorizations')->drop();
		$this->table('oauth_clients')->drop();
	}
}
