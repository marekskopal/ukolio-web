<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Status;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskTemplate;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;

interface TaskTemplateProviderInterface
{
	/** @return list<TaskTemplate> */
	public function getTemplates(Workspace $workspace): array;

	public function getTemplate(Workspace $workspace, int $templateId): ?TaskTemplate;

	public function getTemplateById(int $templateId): ?TaskTemplate;

	public function createFromTask(Task $task, string $name): TaskTemplate;

	public function deleteTemplate(TaskTemplate $template): void;

	public function instantiate(User $author, TaskTemplate $template, Project $project, Status $status, ?string $name = null): Task;
}
