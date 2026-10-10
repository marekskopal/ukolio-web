<?php

declare(strict_types=1);

namespace Ukolio\Service\Authentication\Exception;

use RuntimeException;
use Throwable;

final class GoogleAuthException extends RuntimeException
{
	public function __construct(string $message, ?Throwable $previous = null)
	{
		parent::__construct($message, 0, $previous);
	}
}
