<?php

declare(strict_types=1);

namespace TaskManager\Service\Dbal;

use MarekSkopal\ORM\Database\DatabaseInterface;
use MarekSkopal\ORM\Database\MySqlDatabase;
use MarekSkopal\ORM\Migrations\Migrator;
use MarekSkopal\ORM\ORM;
use MarekSkopal\ORM\Schema\Builder\SchemaBuilder;
use MarekSkopal\ORM\Schema\Schema;

final readonly class DbContext
{
    private DatabaseInterface $database;
    private Schema $schema;
    private ORM $orm;

    public function __construct(string $host, string $name, string $user, string $password)
    {
        $this->database = new MySqlDatabase($host, $user, $password, $name);

        $this->schema = new SchemaBuilder()
            ->addEntityPath(__DIR__ . '/../../Model/Entity')
            ->build();

        $this->orm = new ORM($this->database, $this->schema);
    }

    public function getOrm(): ORM
    {
        return $this->orm;
    }

    public function getDatabase(): DatabaseInterface
    {
        return $this->database;
    }

    public function getMigrator(): Migrator
    {
        return new Migrator(__DIR__ . '/../../../migrations/', $this->database);
    }

    public function getSchema(): Schema
    {
        return $this->schema;
    }
}
