<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Trigger;

use DateTimeImmutable;

interface CronEvaluatorInterface
{
	public function isValid(string $expression): bool;

	public function isDue(string $expression, DateTimeImmutable $now): bool;
}
