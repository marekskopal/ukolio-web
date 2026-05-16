<?php

declare(strict_types=1);

namespace TaskManager\Model\Entity\Enum;

enum TaskPriorityEnum: string
{
    case Low = 'Low';
    case Medium = 'Medium';
    case High = 'High';
}
