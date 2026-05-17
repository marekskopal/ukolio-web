<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ukolio\Dto\CredentialsDto;
use Ukolio\Dto\RefreshTokenDto;
use Ukolio\Dto\SignUpDto;
use Ukolio\Model\Entity\Enum\LocaleEnum;
use Ukolio\Response\ConflictResponse;
use Ukolio\Response\ErrorResponse;
use Ukolio\Response\NotAuthorizedResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Authentication\AuthenticationServiceInterface;
use Ukolio\Service\Authentication\Exception\AuthenticationException;
use Ukolio\Service\Provider\UserProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;
use Ukolio\Validator\PasswordValidator;

final readonly class AuthenticationController
{
	public function __construct(
		private AuthenticationServiceInterface $authenticationService,
		private UserProviderInterface $userProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RoutePost(Routes::AuthenticationLogin->value)]
	public function actionPostLogin(ServerRequestInterface $request): ResponseInterface
	{
		$credentials = $this->requestService->getRequestBodyDto($request, CredentialsDto::class);

		try {
			return new JsonResponse($this->authenticationService->authenticate($credentials));
		} catch (AuthenticationException) {
			return new NotAuthorizedResponse('Email or password is invalid.');
		}
	}

	#[RoutePost(Routes::AuthenticationSignUp->value)]
	public function actionPostSignUp(ServerRequestInterface $request): ResponseInterface
	{
		$signUp = $this->requestService->getRequestBodyDto($request, SignUpDto::class);

		if (!PasswordValidator::isValid($signUp->password)) {
			return new ErrorResponse('Password must be at least 8 characters and contain uppercase, lowercase, and a digit.', 422);
		}

		if ($this->userProvider->getUserByEmail($signUp->email) !== null) {
			return new ConflictResponse('User with email "' . $signUp->email . '" already exists.');
		}

		$locale = $signUp->locale !== null ? LocaleEnum::tryFrom($signUp->locale) ?? LocaleEnum::En : LocaleEnum::En;
		$user = $this->userProvider->createUser($signUp->email, $signUp->password, $signUp->name, $locale);

		$this->workspaceProvider->createWorkspace($user, $signUp->name . "'s Workspace");

		return new JsonResponse($this->authenticationService->authenticate(new CredentialsDto($signUp->email, $signUp->password)));
	}

	#[RoutePost(Routes::AuthenticationRefreshToken->value)]
	public function actionPostRefreshToken(ServerRequestInterface $request): ResponseInterface
	{
		$refreshToken = $this->requestService->getRequestBodyDto($request, RefreshTokenDto::class);

		$tokenKey = (string) getenv('AUTHORIZATION_TOKEN_KEY');

		try {
			$decoded = JWT::decode($refreshToken->refreshToken, new Key($tokenKey, AuthenticationServiceInterface::TokenAlgorithm));
		} catch (ExpiredException) {
			return new NotAuthorizedResponse('RefreshToken is expired.');
		} catch (\UnexpectedValueException | \InvalidArgumentException | \DomainException) {
			return new NotAuthorizedResponse('Invalid RefreshToken.');
		}

		$user = $this->requestService->getUser($request);

		if ($decoded->id !== $user->id) {
			return new NotAuthorizedResponse('Invalid RefreshToken.');
		}

		return new JsonResponse($this->authenticationService->createAuthentication($user));
	}
}
