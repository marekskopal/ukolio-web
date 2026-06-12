<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Trigger;

use Cron\CronExpression;
use DateTimeImmutable;

final readonly class CronEvaluator implements CronEvaluatorInterface
{
	public function isValid(string $expression): bool
	{
		return CronExpression::isValidExpression($expression);
	}

	public function isDue(string $expression, DateTimeImmutable $now): bool
	{
		if (!CronExpression::isValidExpression($expression)) {
			return false;
		}

		return (new CronExpression($expression))->isDue($now);
	}
}
