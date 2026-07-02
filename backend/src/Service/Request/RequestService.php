<?php

declare(strict_types=1);

namespace Ukolio\Service\Request;

use Nette\Utils\Json;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Ukolio\Dto\ArrayFactoryInterface;
use Ukolio\Middleware\AuthorizationMiddleware;
use Ukolio\Model\Entity\User;

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

		/** @var array<mixed> $decodedBody */
		$decodedBody = Json::decode($contents, forceArrays: true);
		return $decodedBody;
	}

	/**
	 * @param class-string<T> $dtoClass
	 * @return T
	 * @template T of ArrayFactoryInterface
	 */
	public function getRequestBodyDto(ServerRequestInterface $request, string $dtoClass): object
	{
		return $dtoClass::fromArray($this->getRequestBody($request));
	}
}
