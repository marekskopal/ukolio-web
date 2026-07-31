<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Server;

use MarekSkopal\ORM\Exception\ORMException;
use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Exception\ExceptionInterface;
use Mcp\Exception\ToolCallException;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Turns domain errors raised by tools into `ToolCallException`, so the agent is told what went wrong.
 *
 * The SDK's `CallToolHandler` renders a `ToolCallException` as a tool result carrying the message,
 * but collapses every other throwable into a bare "Error while executing tool" internal error. Our
 * tools and providers signal expected outcomes ("Task template 1 not found.", "This relation already
 * exists.") with a plain `RuntimeException`, which left agents retrying blind against a message-less
 * failure. Translating here covers every tool in one place, providers included.
 *
 * Infrastructure faults are deliberately not translated: they must stay internal errors so they keep
 * being logged at error level rather than being handed to the agent as if it had asked for something
 * impossible.
 */
final readonly class ToolErrorTranslatingReferenceHandler implements ReferenceHandlerInterface
{
	public function __construct(private ReferenceHandlerInterface $referenceHandler)
	{
	}

	/** @param array<string, mixed> $arguments */
	public function handle(ElementReference $reference, array $arguments): mixed
	{
		try {
			return $this->referenceHandler->handle($reference, $arguments);
		} catch (Throwable $exception) {
			// Caught wide because the interface declares no `@throws` for what the tool itself
			// raises: whatever a tool body throws travels straight through the handler.
			if (!$exception instanceof RuntimeException || $this->isInfrastructureFault($exception)) {
				throw $exception;
			}

			// The code is dropped: HTTP-ish codes carried by domain exceptions mean nothing over
			// JSON-RPC, and the SDK renders only the message into the tool result.
			throw new ToolCallException($exception->getMessage(), previous: $exception);
		}
	}

	/**
	 * `ORMException` and `PDOException` both extend `RuntimeException`, and the SDK signals its own
	 * control flow (including an already-translated `ToolCallException`) with `ExceptionInterface`.
	 * None of those describe something the agent did wrong.
	 */
	private function isInfrastructureFault(RuntimeException $exception): bool
	{
		return $exception instanceof ExceptionInterface
			|| $exception instanceof ORMException
			|| $exception instanceof PDOException;
	}
}
