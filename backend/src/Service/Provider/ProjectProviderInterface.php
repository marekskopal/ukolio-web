<?php

declare(strict_types=1);

namespace TaskManager\Service\Provider;

use Iterator;
use TaskManager\Model\Entity\Project;
use TaskManager\Model\Entity\User;

interface ProjectProviderInterface
{
    /** @return Iterator<Project> */
    public function getProjects(User $user): Iterator;

    public function getProject(User $user, int $projectId): ?Project;

    public function createProject(User $user, string $name, ?string $description): Project;

    public function updateProject(Project $project, string $name, ?string $description): Project;

    public function deleteProject(Project $project): void;
}
