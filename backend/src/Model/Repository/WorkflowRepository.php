<?php

declare(strict_types=1);

namespace TaskManager\Model\Repository;

use MarekSkopal\ORM\Repository\AbstractRepository;
use TaskManager\Model\Entity\Workflow;

/** @extends AbstractRepository<Workflow> */
final class WorkflowRepository extends AbstractRepository
{
    public function findById(int $workflowId): ?Workflow
    {
        return $this->findOne(['id' => $workflowId]);
    }

    public function findByProject(int $projectId): ?Workflow
    {
        return $this->findOne(['project_id' => $projectId]);
    }
}
