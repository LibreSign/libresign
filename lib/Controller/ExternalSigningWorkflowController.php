<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\Signing\ExternalSigningWorkflowService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Provider-neutral, signer-facing, experimental external signing API.
 *
 * Signing tokens arrive in POST bodies, never URLs, redirects or query params.
 * Anonymous requests require a verified bound signer code; account signers
 * require Nextcloud CSRF protection in addition to account identity.
 * Session IDs confer no access without independently revalidated identity.
 *
 * @psalm-import-type LibresignErrorResponse from ResponseDefinitions
 * @psalm-import-type LibresignExternalSigningAvailabilityResponse from ResponseDefinitions
 * @psalm-import-type LibresignExternalSigningSessionResponse from ResponseDefinitions
 * @psalm-import-type LibresignExternalSigningContinueResponse from ResponseDefinitions
 */
final class ExternalSigningWorkflowController extends AEnvironmentAwareOCSController {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private ExternalSigningWorkflowService $workflow,
		private LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * List eligible synthetic laboratory credentials for one authorized signer
	 *
	 * @param string $uuid Current signer SignRequest UUID
	 * @param string $method Bound LibreSign identity method
	 * @param string $identifyValue Exact bound signer identity
	 * @param string $token Verified signer code; never sent in a URL
	 * @param string $integrationId Optional configured integration reference; omit to list eligible providers
	 * @return DataResponse<Http::STATUS_OK, LibresignExternalSigningAvailabilityResponse, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_CONFLICT|Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: Eligible synthetic credentials
	 * 400: Invalid selection parameters
	 * 403: Signer authorization denied
	 * 404: Request or integration unavailable
	 * 409: Unsupported or inactive signing method
	 * 500: Server error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 60)]
	#[UserRateLimit(limit: 30, period: 60)]
	#[OpenAPI(tags: ['signing'])]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/signing/external/{uuid}/availability', requirements: ['apiVersion' => '(v1)'])]
	public function availability(string $uuid, string $method, string $identifyValue, #[\SensitiveParameter] string $token, string $integrationId = ''): DataResponse {
		return $this->execute(function () use ($uuid, $method, $identifyValue, $token, $integrationId): array {
			$result = $this->workflow->availability($uuid, $method, $identifyValue, $token, $integrationId);
			$result['session'] = $this->publicSession($result['session']);
			/** @var LibresignExternalSigningAvailabilityResponse $result */
			return $result;
		}, false);
	}

	/**
	 * Prepare an external synthetic signature without submitting the private key
	 *
	 * @param string $uuid Current signer SignRequest UUID
	 * @param string $method Bound LibreSign identity method
	 * @param string $identifyValue Exact bound signer identity
	 * @param string $token Verified signer code
	 * @param string $integrationId Eligible integration reference
	 * @param string $credentialId Eligible synthetic credential identifier
	 * @param string $attemptId Caller idempotency identifier (not an authorization token)
	 * @param string $profile Supported profile: pdf-basic or pades-baseline-b
	 * @return DataResponse<Http::STATUS_OK, LibresignExternalSigningSessionResponse, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_CONFLICT|Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: Session prepared or safely reused
	 * 400: Invalid credential or request parameters
	 * 403: Signer authorization denied
	 * 404: Request or integration unavailable
	 * 409: Session or document conflict
	 * 500: Server error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[AnonRateLimit(limit: 4, period: 60)]
	#[UserRateLimit(limit: 8, period: 60)]
	#[OpenAPI(tags: ['signing'])]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/signing/external/{uuid}/sessions', requirements: ['apiVersion' => '(v1)'])]
	public function start(string $uuid, string $method, string $identifyValue, #[\SensitiveParameter] string $token,
		string $integrationId, string $credentialId, string $attemptId, string $profile = 'pdf-basic'): DataResponse {
		return $this->execute(fn (): array => ['session' => $this->publicSession(
			$this->workflow->start($uuid, $method, $identifyValue, $token,
				$integrationId, $credentialId, $attemptId, $profile),
		)], false);
	}

	/**
	 * Continue a prepared CSC operation once or reconcile its existing result
	 * Unknown post-signHash outcomes never retry the private key operation.
	 *
	 * @param string $uuid Current signer SignRequest UUID
	 * @param string $method Bound LibreSign identity method
	 * @param string $identifyValue Exact bound signer identity
	 * @param string $token Verified signer code
	 * @param string $sessionId Server-issued CSC session lookup reference
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_ACCEPTED, LibresignExternalSigningContinueResponse, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_CONFLICT|Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: Signed PDF published or terminal status returned
	 * 202: Signing still pending or outcome uncertain; do not retry
	 * 400: Invalid request parameters
	 * 403: Signer authorization denied
	 * 404: Session unavailable
	 * 409: Session or document conflict
	 * 500: Server error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[AnonRateLimit(limit: 5, period: 60)]
	#[UserRateLimit(limit: 12, period: 60)]
	#[OpenAPI(tags: ['signing'])]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/signing/external/{uuid}/continue', requirements: ['apiVersion' => '(v1)'])]
	public function continue(string $uuid, string $method, string $identifyValue,
		#[\SensitiveParameter] string $token, string $sessionId): DataResponse {
		return $this->execute(function () use ($uuid, $method, $identifyValue, $token, $sessionId): array {
			$result = $this->workflow->continue($uuid, $method, $identifyValue, $token, $sessionId);
			$publicSession = $this->publicSession($result['session']);
			if ($publicSession === null) {
				throw new \DomainException('External signing session metadata is missing');
			}
			$result['session'] = $publicSession;
			/** @var LibresignExternalSigningContinueResponse $result */
			return $result;
		}, true);
	}

	/**
	 * Read the current non-secret signing session, rechecking signer identity
	 * Completed documents use LibreSign's established signed-file progress API.
	 *
	 * @param string $uuid Current signer SignRequest UUID
	 * @param string $method Bound LibreSign identity method
	 * @param string $identifyValue Exact bound signer identity
	 * @param string $token Verified signer code
	 * @return DataResponse<Http::STATUS_OK, LibresignExternalSigningSessionResponse, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_CONFLICT|Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: Current session, if any
	 * 400: Invalid parameters
	 * 403: Signer authorization denied
	 * 404: Request unavailable
	 * 409: Workflow conflict
	 * 500: Server error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[PublicPage]
	#[AnonRateLimit(limit: 30, period: 60)]
	#[UserRateLimit(limit: 90, period: 60)]
	#[OpenAPI(tags: ['signing'])]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/signing/external/{uuid}/status', requirements: ['apiVersion' => '(v1)'])]
	public function status(string $uuid, string $method, string $identifyValue, #[\SensitiveParameter] string $token): DataResponse {
		return $this->execute(fn (): array => ['session' => $this->publicSession(
			$this->workflow->status($uuid, $method, $identifyValue, $token),
		)], false);
	}

	/**
	 * Generic terminal-state view with no signer identity or raw PDF/cert data.
	 *
	 * @param array<string, int|string>|null $session
	 * @return array{id:string,status:string,revision:int,integrationId:string,credentialId:string,certificateSha256:string,expiresAt:string}|null
	 */
	private function publicSession(?array $session): ?array {
		if ($session === null) {
			return null;
		}
		return [
			'id' => (string)$session['id'],
			'status' => (string)$session['status'],
			'revision' => (int)($session['revision'] ?? 0),
			'integrationId' => (string)($session['integrationId'] ?? ''),
			'credentialId' => (string)($session['credentialId'] ?? ''),
			'certificateSha256' => (string)($session['certificateSha256'] ?? ''),
			'expiresAt' => (string)($session['expiresAt'] ?? ''),
		];
	}

	/**
	 * @template TData of array<string, mixed>
	 * @template TAllowAccepted of bool
	 * @param callable():TData $operation
	 * @param TAllowAccepted $mayBePending
	 * @return DataResponse<(TAllowAccepted is true ? Http::STATUS_OK|Http::STATUS_ACCEPTED : Http::STATUS_OK), TData, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_CONFLICT|Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 */
	private function execute(callable $operation, bool $mayBePending): DataResponse {
		if ($this->userSession->getUser() !== null && !$this->request->passesCSRFCheck()) {
			return new DataResponse(['error' => 'External signing authorization required'], Http::STATUS_FORBIDDEN);
		}
		try {
			$result = $operation();
			$status = $mayBePending && in_array($result['session']['status'] ?? '', ['uncertain', 'signing_pending', 'authorization_pending'], true)
				? Http::STATUS_ACCEPTED : Http::STATUS_OK;
			return new DataResponse($result, $status);
		} catch (\OutOfBoundsException) {
			return new DataResponse(['error' => 'External signing request not found'], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException) {
			return new DataResponse(['error' => 'Invalid external signing request'], Http::STATUS_BAD_REQUEST);
		} catch (\DomainException) {
			return new DataResponse(['error' => 'External signing request unavailable or conflicted'], Http::STATUS_CONFLICT);
		} catch (\Throwable $e) {
			// Never log signer codes, remote SAD, CMS, PDF bytes or provider secrets.
			$this->logger->error('External signing request failed', ['exception' => $e::class]);
			return new DataResponse(['error' => 'External signing temporarily unavailable'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
