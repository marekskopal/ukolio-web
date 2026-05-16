<?php

declare(strict_types=1);

namespace TaskManager\Service\Provider;

use DateTimeImmutable;
use Iterator;
use TaskManager\Model\Entity\Enum\EventTypeEnum;
use TaskManager\Model\Entity\Enum\TaskPriorityEnum;
use TaskManager\Model\Entity\Project;
use TaskManager\Model\Entity\Status;
use TaskManager\Model\Entity\Task;
use TaskManager\Model\Entity\User;
use TaskManager\Model\Repository\TaskRepository;

final readonly class TaskProvider implements TaskProviderInterface
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EventProviderInterface $eventProvider,
    ) {
    }

    public function getTask(int $taskId): ?Task
    {
        return $this->taskRepository->findById($taskId);
    }

    /** @return Iterator<Task> */
    public function getTasksByProject(Project $project): Iterator
    {
        return $this->taskRepository->findByProject($project->id);
    }

    public function createTask(
        User $author,
        Project $project,
        Status $status,
        string $name,
        ?string $description,
        TaskPriorityEnum $priority,
        ?DateTimeImmutable $dueDate,
    ): Task {
        $position = $this->nextPosition($status);

        $now = new DateTimeImmutable();
        $task = new Task(
            project: $project,
            status: $status,
            name: $name,
            description: $description,
            priority: $priority,
            dueDate: $dueDate,
            position: $position,
        );
        $task->createdAt = $now;
        $task->updatedAt = $now;

        $this->taskRepository->persist($task);

        $this->eventProvider->recordEvent(
            $author,
            $project,
            EventTypeEnum::TaskCreated,
            ['name' => $name, 'statusId' => $status->id, 'statusName' => $status->name],
            $task->id,
        );

        return $task;
    }

    public function updateTask(
        User $author,
        Task $task,
        string $name,
        ?string $description,
        TaskPriorityEnum $priority,
        ?DateTimeImmutable $dueDate,
        Status $status,
    ): Task {
        $oldName = $task->name;
        $statusChanged = $task->status->id !== $status->id;

        $task->name = $name;
        $task->description = $description;
        $task->priority = $priority;
        $task->dueDate = $dueDate;
        if ($statusChanged) {
            $task->status = $status;
            $task->position = $this->nextPosition($status);
        }
        $task->updatedAt = new DateTimeImmutable();
        $this->taskRepository->persist($task);

        $this->eventProvider->recordEvent(
            $author,
            $task->project,
            EventTypeEnum::TaskUpdated,
            ['name' => $name, 'oldName' => $oldName],
            $task->id,
        );

        return $task;
    }

    public function moveTask(User $author, Task $task, Status $newStatus, int $newPosition): Task
    {
        $fromStatus = $task->status;
        $fromPosition = $task->position;
        $sameColumn = $fromStatus->id === $newStatus->id;

        if ($sameColumn) {
            $this->reorderWithinColumn($task, $newPosition);
        } else {
            $this->closeGapInOldColumn($task);
            $this->openSlotInNewColumn($newStatus, $newPosition);
            $task->status = $newStatus;
            $task->position = $newPosition;
        }
        $task->updatedAt = new DateTimeImmutable();
        $this->taskRepository->persist($task);

        $this->eventProvider->recordEvent(
            $author,
            $task->project,
            EventTypeEnum::TaskMoved,
            [
                'fromStatusId' => $fromStatus->id,
                'fromStatusName' => $fromStatus->name,
                'toStatusId' => $newStatus->id,
                'toStatusName' => $newStatus->name,
                'fromPosition' => $fromPosition,
                'toPosition' => $newPosition,
                'taskName' => $task->name,
            ],
            $task->id,
        );

        return $task;
    }

    public function deleteTask(User $author, Task $task): void
    {
        $this->eventProvider->recordEvent(
            $author,
            $task->project,
            EventTypeEnum::TaskDeleted,
            ['name' => $task->name],
            $task->id,
        );

        $this->taskRepository->delete($task);
    }

    private function reorderWithinColumn(Task $task, int $newPosition): void
    {
        $oldPosition = $task->position;
        if ($oldPosition === $newPosition) {
            return;
        }

        foreach ($this->taskRepository->findByStatus($task->status->id) as $sibling) {
            if ($sibling->id === $task->id) {
                continue;
            }

            if ($oldPosition < $newPosition) {
                if ($sibling->position > $oldPosition && $sibling->position <= $newPosition) {
                    $sibling->position--;
                    $sibling->updatedAt = new DateTimeImmutable();
                    $this->taskRepository->persist($sibling);
                }
            } else {
                if ($sibling->position >= $newPosition && $sibling->position < $oldPosition) {
                    $sibling->position++;
                    $sibling->updatedAt = new DateTimeImmutable();
                    $this->taskRepository->persist($sibling);
                }
            }
        }

        $task->position = $newPosition;
    }

    private function closeGapInOldColumn(Task $task): void
    {
        foreach ($this->taskRepository->findByStatus($task->status->id) as $sibling) {
            if ($sibling->id === $task->id) {
                continue;
            }
            if ($sibling->position > $task->position) {
                $sibling->position--;
                $sibling->updatedAt = new DateTimeImmutable();
                $this->taskRepository->persist($sibling);
            }
        }
    }

    private function openSlotInNewColumn(Status $newStatus, int $newPosition): void
    {
        foreach ($this->taskRepository->findByStatus($newStatus->id) as $sibling) {
            if ($sibling->position >= $newPosition) {
                $sibling->position++;
                $sibling->updatedAt = new DateTimeImmutable();
                $this->taskRepository->persist($sibling);
            }
        }
    }

    private function nextPosition(Status $status): int
    {
        $tasks = iterator_to_array($this->taskRepository->findByStatus($status->id), false);
        if ($tasks === []) {
            return 0;
        }
        $max = 0;
        foreach ($tasks as $t) {
            if ($t->position > $max) {
                $max = $t->position;
            }
        }
        return $max + 1;
    }
}
