<?php

declare(strict_types=1);

namespace Ukolio\Service\Request;

use ErrorException;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use TypeError;
use Ukolio\Dto\ArrayFactoryInterface;
use Ukolio\Middleware\AuthorizationMiddleware;
use Ukolio\Model\Entity\User;
use ValueError;

final readonly class RequestService implements RequestServiceInterface
{
	// JSON endpoints never need anything near the 100 MB proxy upload limit
	// (file uploads are multipart and bypass this parser).
	public const int MaxJsonBodyBytes = 1048576;

	public function getUser(ServerRequestInterface $request): User
	{
		$user = $request->getAttribute(AuthorizationMiddleware::AttributeUser);
		assert($user instanceof User);
		return $user;
	}

	/** @return array<mixed> */
	public function getRequestBody(ServerRequestInterface $request): array
	{
		$contents = $request->getBody()->getContents();
		if (strlen($contents) > self::MaxJsonBodyBytes) {
			throw new RuntimeException('Request body is too large.', 413);
		}

		try {
			/** @var array<mixed> $decodedBody */
			$decodedBody = Json::decode($contents, forceArrays: true);
		} catch (JsonException $e) {
			throw new RuntimeException('Request body is not valid JSON.', 400, $e);
		}

		return $decodedBody;
	}

	/**
	 * @param class-string<T> $dtoClass
	 * @return T
	 * @template T of ArrayFactoryInterface
	 */
	public function getRequestBodyDto(ServerRequestInterface $request, string $dtoClass): object
	{
		$body = $this->getRequestBody($request);

		// DTO `fromArray` reads keys unguarded, so a missing key (warning) or wrong type
		// (TypeError/ValueError) is a malformed *client* request — surface it as 400, not a
		// 500 with an error-level log entry. The warning is promoted here rather than relying
		// on whichever global error handler is installed (Tracy in prod, PHPUnit in tests).
		set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
			throw new ErrorException($message, 0, $severity, $file, $line);
		});

		try {
			return $dtoClass::fromArray($body);
		} catch (TypeError | ValueError | ErrorException $e) {
			throw new RuntimeException('Request body is missing required fields or has invalid field types.', 400, $e);
		} finally {
			restore_error_handler();
		}
	}
}
