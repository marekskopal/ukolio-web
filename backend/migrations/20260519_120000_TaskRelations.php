<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class TaskRelationsMigration extends Migration
{
	public function up(): void
	{
		$this->table('task_relations')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('source_task_id', Type::Int, size: 11)
			->addColumn('target_task_id', Type::Int, size: 11)
			->addColumn('type', Type::Enum, enum: ['Related', 'Duplicates', 'Parent', 'DependsOn'])
			->addColumn('created_by_id', Type::Int, size: 11, nullable: true)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['source_task_id', 'target_task_id', 'type'], 'task_relations_unique', true)
			->addIndex(['target_task_id'], 'task_relations_target_id_index', false)
			->addForeignKey('source_task_id', 'tasks', 'id', 'task_relations_source_task_id_fk')
			->addForeignKey('target_task_id', 'tasks', 'id', 'task_relations_target_task_id_fk')
			->addForeignKey('created_by_id', 'users', 'id', 'task_relations_created_by_id_fk')
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
			. "'TaskRelationCreated','TaskRelationDeleted'"
			. ') NOT NULL',
		);
	}

	public function down(): void
	{
		$this->table('task_relations')->drop();

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
			. "'TaskFileAdded','TaskFileDeleted'"
			. ') NOT NULL',
		);
	}
}
