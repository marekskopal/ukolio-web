<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class InitMigration extends Migration
{
	public function up(): void
	{
		$this->table('users')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('email', Type::String)
			->addColumn('password', Type::String)
			->addColumn('name', Type::String)
			->addColumn('current_workspace_id', Type::Int, size: 11, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['email'], 'users_email_unique', true)
			->create();

		$this->table('workspaces')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('owner_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['owner_id'], 'workspaces_owner_id_index', false)
			->addForeignKey('owner_id', 'users', 'id', 'workspaces_owner_id_users_id_fk')
			->create();

		$this->table('workspace_users')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('user_id', Type::Int, size: 11)
			->addColumn('role', Type::Enum, enum: ['Owner', 'Member'])
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workspace_id', 'user_id'], 'workspace_users_unique', true)
			->addIndex(['user_id'], 'workspace_users_user_id_index', false)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'workspace_users_workspace_id_fk')
			->addForeignKey('user_id', 'users', 'id', 'workspace_users_user_id_fk')
			->create();

		$this->table('invitations')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('inviter_id', Type::Int, size: 11)
			->addColumn('email', Type::String)
			->addColumn('token_hash', Type::String, size: 64)
			->addColumn('role', Type::Enum, enum: ['Owner', 'Member'])
			->addColumn('expires_at', Type::Timestamp)
			->addColumn('accepted_at', Type::Timestamp, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['token_hash'], 'invitations_token_hash_unique', true)
			->addIndex(['workspace_id'], 'invitations_workspace_id_index', false)
			->addIndex(['email'], 'invitations_email_index', false)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'invitations_workspace_id_fk')
			->addForeignKey('inviter_id', 'users', 'id', 'invitations_inviter_id_fk')
			->create();

		$this->table('projects')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('description', Type::Text, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workspace_id'], 'projects_workspace_id_index', false)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'projects_workspace_id_fk')
			->create();

		$this->table('workflows')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('project_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['project_id'], 'workflows_project_id_index', false)
			->addForeignKey('project_id', 'projects', 'id', 'workflows_project_id_projects_id_fk')
			->create();

		$this->table('statuses')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workflow_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('color', Type::String, size: 7)
			->addColumn('position', Type::Int)
			->addColumn('type', Type::Enum, enum: ['Start', 'Normal', 'Finish'])
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workflow_id'], 'statuses_workflow_id_index', false)
			->addForeignKey('workflow_id', 'workflows', 'id', 'statuses_workflow_id_workflows_id_fk')
			->create();

		$this->table('tasks')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('project_id', Type::Int, size: 11)
			->addColumn('status_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('description', Type::Text, nullable: true)
			->addColumn('priority', Type::Enum, enum: ['Low', 'Medium', 'High'], default: 'Medium')
			->addColumn('due_date', Type::Date, nullable: true)
			->addColumn('position', Type::Int)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['project_id'], 'tasks_project_id_index', false)
			->addIndex(['status_id'], 'tasks_status_id_index', false)
			->addForeignKey('project_id', 'projects', 'id', 'tasks_project_id_projects_id_fk')
			->addForeignKey('status_id', 'statuses', 'id', 'tasks_status_id_statuses_id_fk')
			->create();

		$this->table('events')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('author_id', Type::Int, size: 11)
			->addColumn('project_id', Type::Int, size: 11)
			->addColumn('task_id', Type::Int, size: 11, nullable: true)
			->addColumn(
				'type',
				Type::Enum,
				enum: [
					'ProjectCreated',
					'ProjectUpdated',
					'ProjectDeleted',
					'WorkflowUpdated',
					'StatusCreated',
					'StatusUpdated',
					'StatusDeleted',
					'StatusMoved',
					'TaskCreated',
					'TaskUpdated',
					'TaskDeleted',
					'TaskMoved',
				],
			)
			->addColumn('metadata', Type::Text)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['project_id'], 'events_project_id_index', false)
			->addIndex(['author_id'], 'events_author_id_index', false)
			->addForeignKey('author_id', 'users', 'id', 'events_author_id_users_id_fk')
			->addForeignKey('project_id', 'projects', 'id', 'events_project_id_projects_id_fk')
			->create();

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
		$this->table('events')->drop();
		$this->table('tasks')->drop();
		$this->table('statuses')->drop();
		$this->table('workflows')->drop();
		$this->table('projects')->drop();
		$this->table('invitations')->drop();
		$this->table('workspace_users')->drop();
		$this->table('workspaces')->drop();
		$this->table('users')->drop();
	}
}
