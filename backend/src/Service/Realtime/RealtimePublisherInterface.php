<?php

declare(strict_types=1);

namespace Ukolio\Service\Realtime;

use Ukolio\Model\Entity\Enum\EventTypeEnum;

interface RealtimePublisherInterface
{
	public function publish(
		EventTypeEnum $type,
		int $workspaceId,
		?int $projectId = null,
		?int $taskId = null,
		?int $commentId = null,
		?int $fileId = null,
		?int $relationId = null,
	): void;
}
