<?php

declare(strict_types=1);

namespace Migrations;

use MarekSkopal\ORM\Migrations\Migration\Migration;

final class ProjectPrefixAndTaskSequenceMigration extends Migration
{
	public function up(): void
	{
		$pdo = $this->databaseProvider->getDatabase()->getPdo();

		$pdo->exec('ALTER TABLE projects ADD COLUMN prefix VARCHAR(16) NOT NULL DEFAULT ""');
		$pdo->exec('UPDATE projects SET prefix = UPPER(SUBSTRING(REPLACE(name, " ", ""), 1, 4))');

		$pdo->exec('ALTER TABLE tasks ADD COLUMN sequence_number INT NOT NULL DEFAULT 0');
		$pdo->exec('SET @row = 0, @prev = 0');
		$pdo->exec(
			'UPDATE tasks t '
			. 'JOIN ('
			. '	SELECT id, project_id, '
			. '	(@row := IF(@prev = project_id, @row + 1, 1)) AS seq, '
			. '	(@prev := project_id) AS p '
			. '	FROM tasks ORDER BY project_id, id'
			. ') s ON s.id = t.id '
			. 'SET t.sequence_number = s.seq',
		);
	}

	public function down(): void
	{
		$pdo = $this->databaseProvider->getDatabase()->getPdo();

		$pdo->exec('ALTER TABLE tasks DROP COLUMN sequence_number');
		$pdo->exec('ALTER TABLE projects DROP COLUMN prefix');
	}
}
