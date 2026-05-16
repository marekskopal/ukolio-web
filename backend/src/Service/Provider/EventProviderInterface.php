<?php

declare(strict_types=1);

namespace TaskManager\Service\Provider;

use Iterator;
use TaskManager\Model\Entity\Enum\EventTypeEnum;
use TaskManager\Model\Entity\Event;
use TaskManager\Model\Entity\Project;
use TaskManager\Model\Entity\User;

interface EventProviderInterface
{
    /** @param array<string,mixed> $metadata */
    public function recordEvent(User $author, Project $project, EventTypeEnum $type, array $metadata, ?int $taskId = null): Event;

    /** @return Iterator<Event> */
    public function getEvents(Project $project, int $limit = 100, int $offset = 0): Iterator;
}
