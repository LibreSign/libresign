<?php

declare(strict_types=1);

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Entitlement\EntitlementService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class EntitlementController extends AEnvironmentAwareController {

	private EntitlementService $entitlementService;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		EntitlementService $entitlementService,
		IUserSession $userSession,
		LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->entitlementService = $entitlementService;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Check entitlement for current user
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(
		verb: 'GET',
		url: '/api/{apiVersion}/entitlement/check',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function check(string $productCode): DataResponse {

		try {
			$user = $this->userSession->getUser();

			if (!$user) {
				return new DataResponse([
					'allowed' => false,
					'error' => 'Unauthorized'
				], Http::STATUS_UNAUTHORIZED);
			}

			if ($productCode === '') {
				return new DataResponse([
					'allowed' => false,
					'error' => 'Invalid product code'
				], Http::STATUS_BAD_REQUEST);
			}

			$canUse = $this->entitlementService->canUse(
				$user->getUID(),
				$productCode
			);

			return new DataResponse([
				'allowed' => $canUse
			], Http::STATUS_OK);

		} catch (\Throwable $e) {

			$this->logger->error('Entitlement check failed', [
				'exception' => $e
			]);

			return new DataResponse([
				'allowed' => false,
				'error' => 'Unable to verify entitlement'
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Consume entitlement
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(
		verb: 'POST',
		url: '/api/{apiVersion}/entitlement/consume',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function consume(string $productCode): DataResponse {

		try {
			$user = $this->userSession->getUser();

			if (!$user) {
				return new DataResponse([
					'success' => false,
					'error' => 'Unauthorized'
				], Http::STATUS_UNAUTHORIZED);
			}

			$entitlement = $this->entitlementService->consume(
				$user->getUID(),
				$productCode
			);

			return new DataResponse([
				'success' => true,
				'remainingUses' => $entitlement->getRemainingUses()
			], Http::STATUS_OK);

		} catch (\RuntimeException $e) {

			// expected business failure
			return new DataResponse([
				'success' => false,
				'error' => $e->getMessage()
			], Http::STATUS_BAD_REQUEST);

		} catch (\Throwable $e) {

			$this->logger->error('Entitlement consumption failed', [
				'exception' => $e
			]);

			return new DataResponse([
				'success' => false,
				'error' => 'Failed to consume entitlement'
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(
		verb: 'POST',
		url: '/api/{apiVersion}/entitlement/xzy-mspw-cbs',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function consumeAfterSign(
		string $userId,
		string $signUuid,
		string $productCode,
		int $signRequestId,
	): DataResponse {
		try {
			$user = $this->userSession->getUser();

			if (!$user || $user->getUID() !== $userId) {
				throw new \RuntimeException('Unauthorized');
			}

			if ($signUuid === '') {
				throw new \RuntimeException('signUuid is required');
			}

			if ($productCode === '') {
				throw new \RuntimeException('productCode is required');
			}

			if ($signRequestId <= 0) {
				throw new \RuntimeException('signRequestId is required');
			}

			$consumed = $this->entitlementService->consumeAfterSign(
				userId: $userId,
				signUuid: $signUuid,
				productCode: $productCode,
				signRequestId: $signRequestId
			);

			return new DataResponse([
				'success' => true,
				'consumed' => $consumed
			]);

		} catch (\Throwable $e) {
			return new DataResponse([
				'success' => false,
				'error' => $e->getMessage(),
				'trace' => $e->getTrace(),
			], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * (Optional) Create entitlement manually
	 *
	 * Useful for:
	 * - testing
	 * - admin tooling
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[ApiRoute(
		verb: 'POST',
		url: '/api/{apiVersion}/entitlement/create',
		requirements: ['apiVersion' => '(v1)']
	)]
	public function create(string $userId, string $productCode, int $uses = 1): DataResponse {
		try {
			$entitlement = $this->entitlementService->create($userId, $productCode, $uses);

			return new DataResponse([
				'entitlement' => $entitlement
			], Http::STATUS_OK);

		} catch (\Throwable $e) {
			$this->logger->error('Entitlement consumption failed', [
				'exception' => $e
			]);

			return new DataResponse([
				'success' => false,
				'error' => 'Failed to create entitlement'
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
