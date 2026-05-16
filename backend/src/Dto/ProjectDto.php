<?php

declare(strict_types=1);

namespace TaskManager\Dto;

use TaskManager\Model\Entity\Project;

final readonly class ProjectDto
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Project $project): self
    {
        return new self(
            id: $project->id,
            name: $project->name,
            description: $project->description,
            createdAt: $project->createdAt->format(DATE_ATOM),
            updatedAt: $project->updatedAt->format(DATE_ATOM),
        );
    }
}
