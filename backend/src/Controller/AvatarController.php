<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Stream;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Ukolio\Dto\UserDto;
use Ukolio\Response\ErrorResponse;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Avatar\AvatarServiceInterface;
use Ukolio\Service\Avatar\InvalidAvatarException;
use Ukolio\Service\Request\RequestServiceInterface;
use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_OK;

final readonly class AvatarController
{
	public function __construct(private AvatarServiceInterface $avatarService, private RequestServiceInterface $requestService,)
	{
	}

	#[RoutePost(Routes::CurrentUserAvatar->value)]
	public function actionPostAvatar(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);

		$uploaded = $request->getUploadedFiles()['file'] ?? null;
		if (!$uploaded instanceof UploadedFileInterface) {
			return new ErrorResponse('Missing "file" multipart field.', 422);
		}
		if ($uploaded->getError() === UPLOAD_ERR_INI_SIZE || $uploaded->getError() === UPLOAD_ERR_FORM_SIZE) {
			return new ErrorResponse('Avatar image exceeds the 1 MB limit.', 422);
		}
		if ($uploaded->getError() !== UPLOAD_ERR_OK) {
			return new ErrorResponse('Upload failed with code ' . $uploaded->getError() . '.', 422);
		}

		try {
			$updated = $this->avatarService->upload($user, $uploaded->getStream()->getContents());
		} catch (InvalidAvatarException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(UserDto::fromEntity($updated));
	}

	#[RouteDelete(Routes::CurrentUserAvatar->value)]
	public function actionDeleteAvatar(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);

		return new JsonResponse(UserDto::fromEntity($this->avatarService->remove($user)));
	}

	/**
	 * Unauthenticated (see AuthorizationMiddleware): `<img src>` can't send the bearer token. The
	 * random `avatarKey` makes the URL unguessable and rotates on every upload, so the response is
	 * cacheable forever.
	 */
	#[RouteGet(Routes::Avatar->value)]
	public function actionGetAvatar(ServerRequestInterface $request, int $userId, int|string $avatarKey): ResponseInterface
	{
		// JsonStrategy casts digit-only route vars to int; a hex key can (rarely) be all digits.
		$bytes = $this->avatarService->readContent($userId, (string) $avatarKey);
		if ($bytes === null) {
			return new NotFoundResponse('Avatar not found.');
		}

		$stream = new Stream('php://temp', 'wb+');
		$stream->write($bytes);
		$stream->rewind();

		return new Response($stream, 200, [
			'Content-Type' => 'image/webp',
			'Content-Length' => (string) strlen($bytes),
			'Cache-Control' => 'public, max-age=31536000, immutable',
			'X-Content-Type-Options' => 'nosniff',
		]);
	}
}
