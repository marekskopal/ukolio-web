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
use RuntimeException;
use Ukolio\Dto\ConfirmPasswordResetDto;
use Ukolio\Dto\CredentialsDto;
use Ukolio\Dto\RefreshTokenDto;
use Ukolio\Dto\RequestPasswordResetDto;
use Ukolio\Dto\SignUpDto;
use Ukolio\Dto\VerifyEmailDto;
use Ukolio\Model\Entity\Enum\LocaleEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Response\ConflictResponse;
use Ukolio\Response\ErrorResponse;
use Ukolio\Response\NotAuthorizedResponse;
use Ukolio\Response\OkResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Authentication\AuthenticationServiceInterface;
use Ukolio\Service\Authentication\Exception\AccountLockedException;
use Ukolio\Service\Authentication\Exception\AuthenticationException;
use Ukolio\Service\Provider\EmailVerificationProviderInterface;
use Ukolio\Service\Provider\PasswordResetProviderInterface;
use Ukolio\Service\Provider\UserProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Realtime\MercureCookieIssuerInterface;
use Ukolio\Service\Request\RequestServiceInterface;
use Ukolio\Validator\PasswordValidator;

final readonly class AuthenticationController
{
	public function __construct(
		private AuthenticationServiceInterface $authenticationService,
		private UserProviderInterface $userProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PasswordResetProviderInterface $passwordResetProvider,
		private EmailVerificationProviderInterface $emailVerificationProvider,
		private RequestServiceInterface $requestService,
		private MercureCookieIssuerInterface $mercureCookieIssuer,
	) {
	}

	#[RoutePost(Routes::AuthenticationLogin->value)]
	public function actionPostLogin(ServerRequestInterface $request): ResponseInterface
	{
		$credentials = $this->requestService->getRequestBodyDto($request, CredentialsDto::class);

		try {
			$auth = $this->authenticationService->authenticate($credentials);
		} catch (AccountLockedException $e) {
			return new ErrorResponse(
				'Too many failed sign-in attempts. Please try again later.',
				429,
				['Retry-After' => (string) $e->retryAfterSeconds],
			);
		} catch (AuthenticationException) {
			return new NotAuthorizedResponse('Email or password is invalid.');
		}

		$user = $this->userProvider->getUser($auth->userId);

		return $this->withMercureCookie(new JsonResponse($auth), $request, $user);
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

		$this->emailVerificationProvider->requestVerification($user);

		$auth = $this->authenticationService->authenticate(new CredentialsDto($signUp->email, $signUp->password));

		return $this->withMercureCookie(new JsonResponse($auth), $request, $user);
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

		return $this->withMercureCookie(
			new JsonResponse($this->authenticationService->createAuthentication($user)),
			$request,
			$user,
		);
	}

	#[RoutePost(Routes::AuthenticationRequestPasswordReset->value)]
	public function actionPostRequestPasswordReset(ServerRequestInterface $request): ResponseInterface
	{
		$dto = $this->requestService->getRequestBodyDto($request, RequestPasswordResetDto::class);

		$this->passwordResetProvider->requestReset($dto->email);

		return new OkResponse();
	}

	#[RoutePost(Routes::AuthenticationConfirmPasswordReset->value)]
	public function actionPostConfirmPasswordReset(ServerRequestInterface $request): ResponseInterface
	{
		$dto = $this->requestService->getRequestBodyDto($request, ConfirmPasswordResetDto::class);

		if (!PasswordValidator::isValid($dto->password)) {
			return new ErrorResponse('Password must be at least 8 characters and contain uppercase, lowercase, and a digit.', 422);
		}

		$token = $this->passwordResetProvider->findByToken($dto->token);
		if ($token === null) {
			return new ErrorResponse('This reset link is invalid.', 422);
		}

		try {
			$user = $this->passwordResetProvider->confirmReset($token, $dto->password);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return $this->withMercureCookie(
			new JsonResponse($this->authenticationService->createAuthentication($user)),
			$request,
			$user,
		);
	}

	#[RoutePost(Routes::AuthenticationVerifyEmail->value)]
	public function actionPostVerifyEmail(ServerRequestInterface $request): ResponseInterface
	{
		$dto = $this->requestService->getRequestBodyDto($request, VerifyEmailDto::class);

		$token = $this->emailVerificationProvider->findByToken($dto->token);
		if ($token === null) {
			return new ErrorResponse('This verification link is invalid.', 422);
		}

		try {
			$this->emailVerificationProvider->confirmVerification($token);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new OkResponse();
	}

	private function withMercureCookie(ResponseInterface $response, ServerRequestInterface $request, ?User $user,): ResponseInterface
	{
		if ($user === null) {
			return $response;
		}

		return $response->withAddedHeader(
			'Set-Cookie',
			$this->mercureCookieIssuer->issue($user, $this->isSecureRequest($request)),
		);
	}

	private function isSecureRequest(ServerRequestInterface $request): bool
	{
		$forwardedProto = $request->getHeader('X-Forwarded-Proto')[0] ?? null;
		if ($forwardedProto !== null) {
			return strtolower($forwardedProto) === 'https';
		}

		return strtolower($request->getUri()->getScheme()) === 'https';
	}
}
