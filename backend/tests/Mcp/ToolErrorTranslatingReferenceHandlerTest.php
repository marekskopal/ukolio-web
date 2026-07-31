<?php

declare(strict_types=1);

namespace Ukolio\Tests\Mcp;

use MarekSkopal\ORM\Exception\QueryException;
use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Exception\ToolCallException;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use Ukolio\Mcp\Server\ToolErrorTranslatingReferenceHandler;

#[CoversClass(ToolErrorTranslatingReferenceHandler::class)]
final class ToolErrorTranslatingReferenceHandlerTest extends TestCase
{
	public function testResultPassesThrough(): void
	{
		$handler = new ToolErrorTranslatingReferenceHandler($this->handlerReturning('done'));

		self::assertSame('done', $handler->handle($this->reference(), []));
	}

	/**
	 * The message is what the agent gets to read — without it, `CallToolHandler` reports a bare
	 * "Error while executing tool" and the caller has nothing to correct.
	 */
	public function testDomainErrorBecomesToolCallExceptionCarryingTheMessage(): void
	{
		$domainError = new RuntimeException('Task template 1 not found.');
		$handler = new ToolErrorTranslatingReferenceHandler($this->handlerThrowing($domainError));

		try {
			$handler->handle($this->reference(), []);
			self::fail('Expected a ToolCallException.');
		} catch (ToolCallException $exception) {
			self::assertSame('Task template 1 not found.', $exception->getMessage());
			self::assertSame($domainError, $exception->getPrevious());
		}
	}

	public function testAlreadyTranslatedErrorIsNotWrappedAgain(): void
	{
		$toolCallError = new ToolCallException('This relation already exists.');
		$handler = new ToolErrorTranslatingReferenceHandler($this->handlerThrowing($toolCallError));

		try {
			$handler->handle($this->reference(), []);
			self::fail('Expected a ToolCallException.');
		} catch (ToolCallException $exception) {
			self::assertSame($toolCallError, $exception);
			self::assertNull($exception->getPrevious());
		}
	}

	/**
	 * ORM and PDO failures extend RuntimeException too, but they are server faults: they have to stay
	 * internal errors so they keep being logged at error level instead of being blamed on the agent.
	 */
	public function testOrmFailureIsNotTranslated(): void
	{
		$queryError = new QueryException(new PDOException('MySQL server has gone away'), 'SELECT 1');
		$handler = new ToolErrorTranslatingReferenceHandler($this->handlerThrowing($queryError));

		$this->expectExceptionObject($queryError);
		$handler->handle($this->reference(), []);
	}

	public function testPdoFailureIsNotTranslated(): void
	{
		$pdoError = new PDOException('SQLSTATE[HY000]: General error: 2006');
		$handler = new ToolErrorTranslatingReferenceHandler($this->handlerThrowing($pdoError));

		$this->expectExceptionObject($pdoError);
		$handler->handle($this->reference(), []);
	}

	private function reference(): ElementReference
	{
		return new ElementReference(static fn (): string => 'unused');
	}

	private function handlerThrowing(Throwable $exception): ReferenceHandlerInterface
	{
		return new class ($exception) implements ReferenceHandlerInterface {
			public function __construct(private readonly Throwable $exception)
			{
			}

			/** @param array<string, mixed> $arguments */
			public function handle(ElementReference $reference, array $arguments): mixed
			{
				throw $this->exception;
			}
		};
	}

	private function handlerReturning(mixed $result): ReferenceHandlerInterface
	{
		return new class ($result) implements ReferenceHandlerInterface {
			public function __construct(private readonly mixed $result)
			{
			}

			/** @param array<string, mixed> $arguments */
			public function handle(ElementReference $reference, array $arguments): mixed
			{
				return $this->result;
			}
		};
	}
}
