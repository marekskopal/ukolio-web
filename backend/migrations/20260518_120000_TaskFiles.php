<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Enum\Type;
use MarekSkopal\ORM\Migrations\Migration\Migration;

final class TaskFilesMigration extends Migration
{
	public function up(): void
	{
		$this->table('task_files')
			->addColumn('id', Type::Int, autoincrement: true, primary: true)
			->addColumn('task_id', Type::Int, size: 11)
			->addColumn('filename', Type::String)
			->addColumn('mime_type', Type::String)
			->addColumn('size', Type::Int)
			->addColumn('storage_key', Type::String, size: 512)
			->addColumn('uploaded_by_user_id', Type::Int, size: 11, nullable: true)
			->addColumn('uploaded_by_agent', Type::Boolean, default: false)
			->addColumn('created_at', Type::Timestamp)
			->addColumn('updated_at', Type::Timestamp)
			->addIndex(['task_id'], 'task_files_task_id_index', false)
			->addForeignKey('task_id', 'tasks', 'id', 'task_files_task_id_fk')
			->addForeignKey('uploaded_by_user_id', 'users', 'id', 'task_files_uploaded_by_user_id_fk')
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
			. "'TaskFileAdded','TaskFileDeleted'"
			. ') NOT NULL',
		);
	}

	public function down(): void
	{
		$this->table('task_files')->drop();

		$pdo = $this->databaseProvider->getDatabase()->getPdo();
		$pdo->exec(
			'ALTER TABLE events MODIFY COLUMN type ENUM('
			. "'ProjectCreated','ProjectUpdated','ProjectDeleted',"
			. "'WorkflowUpdated',"
			. "'StatusCreated','StatusUpdated','StatusDeleted','StatusMoved',"
			. "'TaskCreated','TaskUpdated','TaskDeleted','TaskMoved',"
			. "'MemberRoleChanged','OwnershipTransferred',"
			. "'AdminDeletedWorkspace','AdminDeletedUser','AdminChangedSystemRole',"
			. "'FieldCreated','FieldUpdated','FieldDeleted','ProjectFieldsUpdated'"
			. ') NOT NULL',
		);
	}
}
