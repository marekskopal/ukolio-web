<?php

declare(strict_types=1);

namespace TaskManager\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use TaskManager\Model\Entity\Task;

/** @extends AbstractRepository<Task> */
final class TaskRepository extends AbstractRepository
{
    public function findById(int $taskId): ?Task
    {
        return $this->findOne(['id' => $taskId]);
    }

    /** @return Iterator<Task> */
    public function findByProject(int $projectId): Iterator
    {
        return $this->select()
            ->where(['project_id' => $projectId])
            ->orderBy('status_id', 'ASC')
            ->orderBy('position', 'ASC')
            ->fetchAll();
    }

    /** @return Iterator<Task> */
    public function findByStatus(int $statusId): Iterator
    {
        return $this->select()
            ->where(['status_id' => $statusId])
            ->orderBy('position', 'ASC')
            ->fetchAll();
    }
}
