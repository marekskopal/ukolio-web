<?php

declare(strict_types=1);

namespace TaskManager\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use TaskManager\Model\Entity\Project;

/** @extends AbstractRepository<Project> */
final class ProjectRepository extends AbstractRepository
{
    /** @return Iterator<Project> */
    public function findProjectsByUser(int $userId): Iterator
    {
        return $this->select()
            ->where(['user_id' => $userId])
            ->orderBy('id', 'DESC')
            ->fetchAll();
    }

    public function findProject(int $userId, int $projectId): ?Project
    {
        return $this->findOne(['user_id' => $userId, 'id' => $projectId]);
    }
}
