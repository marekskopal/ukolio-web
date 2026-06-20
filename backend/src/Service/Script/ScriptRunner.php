<?php

declare(strict_types=1);

namespace Ukolio\Service\Script;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Throwable;
use Ukolio\Model\Entity\Enum\ScriptRunStatusEnum;
use Ukolio\Model\Entity\Enum\ScriptTriggerEnum;
use Ukolio\Model\Entity\Script;
use Ukolio\Model\Entity\ScriptRun;
use Ukolio\Model\Repository\ScriptRepository;
use Ukolio\Model\Repository\ScriptRunRepository;
use Ukolio\Service\Script\Engine\ScriptEngineInterface;
use Ukolio\Service\Script\Host\ScriptHostApiFactory;
use Ukolio\Service\Script\Host\ScriptRunContext;

/**
 * Executes a Script in the sandbox and records a ScriptRun. Always runs in a worker (never in an
 * HTTP request). Every run produces a ScriptRun row regardless of outcome.
 */
final readonly class ScriptRunner
{
	private const int TimeLimitMs = 5000;
	private const int MemoryLimitBytes = 67108864;
	private const int MaxHttpCalls = 20;
	private const int MaxTaskApiCalls = 200;

	public function __construct(
		private ScriptEngineInterface $engine,
		private ScriptHostApiFactory $hostApiFactory,
		private ScriptRunRepository $scriptRunRepository,
		private ScriptRepository $scriptRepository,
		private LoggerInterface $logger,
	) {
	}

	/** @param array<string, mixed>|null $event */
	public function run(Script $script, ScriptTriggerEnum $triggerType, ?array $event = null, ?string $scheduledAt = null): ScriptRun
	{
		$startedAt = new DateTimeImmutable();
		$run = new ScriptRun(script: $script, triggerType: $triggerType, status: ScriptRunStatusEnum::Running, startedAt: $startedAt);
		$run->createdAt = $startedAt;
		$run->updatedAt = $startedAt;
		$this->scriptRunRepository->persist($run);

		$context = new ScriptRunContext(
			owner: $script->createdBy,
			workspace: $script->workspace,
			triggerType: $triggerType,
			event: $event,
			scheduledAt: $scheduledAt,
			maxHttpCalls: self::MaxHttpCalls,
			maxTaskApiCalls: self::MaxTaskApiCalls,
		);

		ScriptExecutionGuard::enter();

		try {
			$hostApi = $this->hostApiFactory->create($context);
			$result = $this->engine->execute($script->source, $hostApi, self::TimeLimitMs, self::MemoryLimitBytes);
			$run->status = $result->status;
			$run->error = $result->error;
		} catch (Throwable $e) {
			// Defensive: a host/engine fault must still close out the run row, not crash the worker.
			$this->logger->error('Script run crashed: ' . $e->getMessage(), ['scriptId' => $script->id, 'exception' => $e]);
			$run->status = ScriptRunStatusEnum::Error;
			$run->error = $e->getMessage();
		} finally {
			ScriptExecutionGuard::leave();
		}

		$finishedAt = new DateTimeImmutable();
		$run->logs = $context->getLogs();
		$run->httpCalls = $context->getHttpCalls();
		$run->taskApiCalls = $context->getTaskApiCalls();
		$run->finishedAt = $finishedAt;
		$run->updatedAt = $finishedAt;
		$this->scriptRunRepository->persist($run);

		$script->lastRunAt = $finishedAt;
		$script->updatedAt = $finishedAt;
		$this->scriptRepository->persist($script);

		return $run;
	}
}
