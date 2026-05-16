<?php

declare(strict_types=1);

namespace TaskManager\Service\Logger;

use Psr\Log\AbstractLogger;
use Stringable;

final class StderrLogger extends AbstractLogger
{
    /** @param array<mixed> $context */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $line = sprintf('[%s] %s %s', date('Y-m-d H:i:s'), (string) $level, (string) $message);
        if ($context !== []) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
        fwrite(STDERR, $line . PHP_EOL);
    }
}
