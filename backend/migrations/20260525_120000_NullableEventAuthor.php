<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Migrations\Migration\Migration;

final class NullableEventAuthorMigration extends Migration
{
	public function up(): void
	{
		$pdo = $this->databaseProvider->getDatabase()->getPdo();

		$pdo->exec('ALTER TABLE events DROP FOREIGN KEY events_author_id_users_id_fk');
		$pdo->exec('ALTER TABLE events MODIFY COLUMN author_id INT(11) NULL');
		$pdo->exec(
			'ALTER TABLE events ADD CONSTRAINT events_author_id_users_id_fk '
			. 'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
		);

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
			. "'TaskCommentAdded','TaskCommentDeleted',"
			. "'UserSelfDeleted'"
			. ') NOT NULL',
		);
	}

	public function down(): void
	{
		$pdo = $this->databaseProvider->getDatabase()->getPdo();

		$pdo->exec("DELETE FROM events WHERE type = 'UserSelfDeleted'");
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

		$pdo->exec('DELETE FROM events WHERE author_id IS NULL');
		$pdo->exec('ALTER TABLE events DROP FOREIGN KEY events_author_id_users_id_fk');
		$pdo->exec('ALTER TABLE events MODIFY COLUMN author_id INT(11) NOT NULL');
		$pdo->exec(
			'ALTER TABLE events ADD CONSTRAINT events_author_id_users_id_fk '
			. 'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE',
		);
	}
}
