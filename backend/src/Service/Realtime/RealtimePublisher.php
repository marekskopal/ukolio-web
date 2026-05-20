<?php

declare(strict_types=1);

namespace Ukolio\Service\Realtime;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use const JSON_THROW_ON_ERROR;

final readonly class RealtimePublisher implements RealtimePublisherInterface
{
	public const string TopicPrefix = 'ukolio/workspaces/';

	public function __construct(
		private HubInterface $hub,
		private RealtimeOriginContextInterface $originContext,
		private LoggerInterface $logger,
	) {
	}

	public function publish(
		EventTypeEnum $type,
		int $workspaceId,
		?int $projectId = null,
		?int $taskId = null,
		?int $commentId = null,
		?int $fileId = null,
		?int $relationId = null,
	): void {
		$topic = self::TopicPrefix . $workspaceId;
		$data = json_encode([
			'type' => $type->value,
			'workspaceId' => $workspaceId,
			'projectId' => $projectId,
			'taskId' => $taskId,
			'commentId' => $commentId,
			'fileId' => $fileId,
			'relationId' => $relationId,
			'originClientId' => $this->originContext->get(),
		], JSON_THROW_ON_ERROR);

		try {
			$this->hub->publish(new Update($topic, $data));
		} catch (Throwable $e) {
			// Realtime is best-effort: a hub outage must never break the mutation that triggered it.
			$this->logger->warning('Mercure publish failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}
}
