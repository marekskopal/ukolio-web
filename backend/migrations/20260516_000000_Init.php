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
            ->addColumn('created_at', Type::Timestamp)
            ->addColumn('updated_at', Type::Timestamp)
            ->addIndex(['email'], 'users_email_unique', true)
            ->create();

        $this->table('projects')
            ->addColumn('id', Type::Int, autoincrement: true, primary: true)
            ->addColumn('user_id', Type::Int, size: 11)
            ->addColumn('name', Type::String)
            ->addColumn('description', Type::Text, nullable: true)
            ->addColumn('created_at', Type::Timestamp)
            ->addColumn('updated_at', Type::Timestamp)
            ->addIndex(['user_id'], 'projects_user_id_index', false)
            ->addForeignKey('user_id', 'users', 'id', 'projects_user_id_users_id_fk')
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
                    'ProjectCreated', 'ProjectUpdated', 'ProjectDeleted',
                    'WorkflowUpdated',
                    'StatusCreated', 'StatusUpdated', 'StatusDeleted', 'StatusMoved',
                    'TaskCreated', 'TaskUpdated', 'TaskDeleted', 'TaskMoved',
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
    }

    public function down(): void
    {
        $this->table('events')->drop();
        $this->table('tasks')->drop();
        $this->table('statuses')->drop();
        $this->table('workflows')->drop();
        $this->table('projects')->drop();
        $this->table('users')->drop();
    }
}
