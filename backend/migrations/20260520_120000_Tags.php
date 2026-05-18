<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class TagsMigration extends Migration
{
	public function up(): void
	{
		$this->table('tags')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('workspace_id', Type::Int, size: 11)
			->addColumn('name', Type::String)
			->addColumn('color', Type::String, size: 7)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['workspace_id'], 'tags_workspace_id_index', false)
			->addIndex(['workspace_id', 'name'], 'tags_workspace_name_unique', true)
			->addForeignKey('workspace_id', 'workspaces', 'id', 'tags_workspace_id_fk')
			->create();

		$this->table('task_tags')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('task_id', Type::Int, size: 11)
			->addColumn('tag_id', Type::Int, size: 11)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['task_id'], 'task_tags_task_id_index', false)
			->addIndex(['tag_id'], 'task_tags_tag_id_index', false)
			->addIndex(['task_id', 'tag_id'], 'task_tags_unique', true)
			->addForeignKey('task_id', 'tasks', 'id', 'task_tags_task_id_fk')
			->addForeignKey('tag_id', 'tags', 'id', 'task_tags_tag_id_fk')
			->create();

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

	public function down(): void
	{
		$this->table('task_tags')->drop();
		$this->table('tags')->drop();

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
			. "'TaskRelationCreated','TaskRelationDeleted'"
			. ') NOT NULL',
		);
	}
}
