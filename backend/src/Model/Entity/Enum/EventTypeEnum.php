<?php

declare(strict_types=1);

namespace TaskManager\Model\Entity\Enum;

enum EventTypeEnum: string
{
    case ProjectCreated = 'ProjectCreated';
    case ProjectUpdated = 'ProjectUpdated';
    case ProjectDeleted = 'ProjectDeleted';

    case WorkflowUpdated = 'WorkflowUpdated';

    case StatusCreated = 'StatusCreated';
    case StatusUpdated = 'StatusUpdated';
    case StatusDeleted = 'StatusDeleted';
    case StatusMoved = 'StatusMoved';

    case TaskCreated = 'TaskCreated';
    case TaskUpdated = 'TaskUpdated';
    case TaskDeleted = 'TaskDeleted';
    case TaskMoved = 'TaskMoved';
}
