<?php

declare(strict_types=1);

namespace TaskManager\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use TaskManager\Model\Entity\Event;

/** @extends AbstractRepository<Event> */
final class EventRepository extends AbstractRepository
{
    /** @return Iterator<Event> */
    public function findByProject(int $projectId, int $limit = 100, int $offset = 0): Iterator
    {
        return $this->select()
            ->where(['project_id' => $projectId])
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->fetchAll();
    }
}
