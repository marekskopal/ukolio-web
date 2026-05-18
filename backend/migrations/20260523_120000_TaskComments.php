<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class TaskCommentsMigration extends Migration
{
	public function up(): void
	{
		$this->table('task_comments')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('task_id', Type::Int, size: 11)
			->addColumn('author_id', Type::Int, size: 11)
			->addColumn('body', Type::Text)
			->addColumn('actor_type', Type::String, size: 16, default: 'Human')
			->addColumn('mcp_client_id', Type::String, size: 128, nullable: true)
			->addColumn('mcp_client_name', Type::String, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['task_id'], 'task_comments_task_id_index', false)
			->addIndex(['author_id'], 'task_comments_author_id_index', false)
			->addForeignKey('task_id', 'tasks', 'id', 'task_comments_task_id_fk')
			->addForeignKey('author_id', 'users', 'id', 'task_comments_author_id_fk')
			->create();

		$pdo = $this->databaseProvider->getDatabase()->getPdo();
		$pdo->exec("ALTER TABLE task_comments MODIFY COLUMN actor_type ENUM('Human','Agent') NOT NULL DEFAULT 'Human'");

		$pdo->exec(
			'ALTER TABLE events MODIFY COLUMN type ENUM('
			. "'ProjectCreated','ProjectUpdated','ProjectDeleted',"
			. "'WorkflowUpdated',"
			. "'StatusCreated','StatusUpdated','StatusDeleted','StatusMoved',"
			. "'TaskCreated','TaskUpdated','TaskDeleted','TaskMoved',"
			. "'MemberRoleChanged','OwnershipTransferred',"
			. "'AdminDeletedWorkspace','AdminDeletedUser','AdminChangedSystemRole',"
			. "'FieldCreated','FieldUpdated','FieldDeleted','ProjectFieldsUpdated',"
			. "'TaskFileAdded','TaskFileDeleted',"
			. "'TaskRelationCreated','TaskRelationDeleted',"
			. "'TagCreated','TagUpdated','TagDeleted','TaskTagsUpdated',"
			. "'TaskCommentAdded','TaskCommentDeleted'"
			. ') NOT NULL',
		);
	}

	public function down(): void
	{
		$this->table('task_comments')->drop();

		$pdo = $this->databaseProvider->getDatabase()->getPdo();
		$pdo->exec(
			'ALTER TABLE events MODIFY COLUMN type ENUM('
			. "'ProjectCreated','ProjectUpdated','ProjectDeleted',"
			. "'WorkflowUpdated',"
			. "'StatusCreated','StatusUpdated','StatusDeleted','StatusMoved',"
			. "'TaskCreated','TaskUpdated','TaskDeleted','TaskMoved',"
			. "'MemberRoleChanged','OwnershipTransferred',"
			. "'AdminDeletedWorkspace','AdminDeletedUser','AdminChangedSystemRole',"
			. "'FieldCreated','FieldUpdated','FieldDeleted','ProjectFieldsUpdated',"
			. "'TaskFileAdded','TaskFileDeleted',"
			. "'TaskRelationCreated','TaskRelationDeleted',"
			. "'TagCreated','TagUpdated','TagDeleted','TaskTagsUpdated'"
			. ') NOT NULL',
		);
	}
}
