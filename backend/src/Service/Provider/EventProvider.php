<?php

declare(strict_types=1);

namespace TaskManager\Service\Provider;

use DateTimeImmutable;
use Iterator;
use TaskManager\Model\Entity\Enum\EventTypeEnum;
use TaskManager\Model\Entity\Event;
use TaskManager\Model\Entity\Project;
use TaskManager\Model\Entity\User;
use TaskManager\Model\Repository\EventRepository;

final readonly class EventProvider implements EventProviderInterface
{
    public function __construct(private EventRepository $eventRepository)
    {
    }

    /** @param array<string,mixed> $metadata */
    public function recordEvent(User $author, Project $project, EventTypeEnum $type, array $metadata, ?int $taskId = null): Event
    {
        $now = new DateTimeImmutable();
        $event = new Event(
            author: $author,
            project: $project,
            type: $type,
            metadata: json_encode($metadata, JSON_THROW_ON_ERROR),
            taskId: $taskId,
        );
        $event->createdAt = $now;
        $event->updatedAt = $now;

        $this->eventRepository->persist($event);

        return $event;
    }

    /** @return Iterator<Event> */
    public function getEvents(Project $project, int $limit = 100, int $offset = 0): Iterator
    {
        return $this->eventRepository->findByProject($project->id, $limit, $offset);
    }
}
