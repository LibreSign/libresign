<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\ExternalIntegrationConflictException;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\ExperimentalFeaturesService;
use OCA\Libresign\Service\Signing\External\IntegrationScopeAccess;
use OCA\Libresign\Service\Signing\ExternalIntegrationAdministration;
use OCA\Libresign\Service\Signing\ExternalIntegrationRecord;
use OCA\Libresign\Service\Signing\ExternalIntegrationStore;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscDriver;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscProviderProbe;
use OCA\Libresign\Service\Signing\Provider\ExternalSigningDriverCatalog;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Administrative OCS boundary for configured external signing integrations.
 *
 * Credentials are write-only. No provider communication or signing occurs here.
 *
 * @psalm-import-type LibresignErrorResponse from ResponseDefinitions
 * @psalm-import-type LibresignExternalIntegrationResponse from ResponseDefinitions
 * @psalm-import-type LibresignExternalIntegrationListResponse from ResponseDefinitions
 * @psalm-import-type LibresignExternalIntegrationDeletionResponse from ResponseDefinitions
 * @psalm-import-type LibresignExternalIntegrationProbeResponse from ResponseDefinitions
 */
final class ExternalSigningIntegrationsController extends AEnvironmentAwareOCSController {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private ExperimentalFeaturesService $experimentalFeatures,
		private ExternalSigningDriverCatalog $catalog,
		private ExternalIntegrationAdministration $administration,
		private IntegrationScopeAccess $scopeAccess,
		private CscProviderProbe $providerProbe,
		private LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * List configured external signing integrations with keyset pagination
	 *
	 * List items are summaries: detailed secretFields status is returned by
	 * single-integration reads and mutation responses only.
	 *
	 * @param int $afterId Keyset cursor, zero for the first page
	 * @param int $pageSize Page size between one and 200
	 * @param string $scopeType Connection scope: system, group or user
	 * @param string $scopeId Group ID or account UID; empty for system
	 * @return DataResponse<Http::STATUS_OK, LibresignExternalIntegrationListResponse, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_CONFLICT|Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: Page of integrations
	 * 400: Invalid pagination
	 * 401: Authentication required
	 * 403: Administrator not delegated for this connection scope
	 * 404: External signing is not enabled
	 * 409: Integration conflict
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[OpenAPI(tags: ['system'])]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/signing/integrations', requirements: ['apiVersion' => '(v1)'])]
	public function index(int $afterId = 0, int $pageSize = 50, string $scopeType = 'system', string $scopeId = ''): DataResponse {
		if (($denied = $this->authorizeScope($scopeType, $scopeId)) !== null) {
			return $denied;
		}

		return $this->execute(function () use ($afterId, $pageSize, $scopeType, $scopeId): array {
			$page = $this->administration->listPage($afterId, $pageSize, $scopeType, $scopeId);
			$drivers = $this->driversById();
			$items = [];
			foreach ($page['items'] as $record) {
				$item = $record->jsonSerialize();
				$item['driverAvailable'] = ($drivers[$record->driverId]['available'] ?? false) === true;
				$items[] = $item;
			}
			/** @var LibresignExternalIntegrationListResponse $result */
			$result = ['items' => $items, 'nextCursor' => $page['nextCursor']];
			return $result;
		});
	}

	/**
	 * Create a disabled integration with write-only credentials
	 *
	 * @param string $driverId Registered driver identifier
	 * @param string $name Administrator-defined display name
	 * @param array<string, string> $configuration Non-secret driver settings
	 * @param array<string, string> $secrets Write-only credential values
	 * @param string $scopeType Connection scope: system, group or user
	 * @param string $scopeId Group ID or account UID; empty for system
	 * @return DataResponse<Http::STATUS_CREATED, LibresignExternalIntegrationResponse, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_CONFLICT|Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 201: Integration created, initially disabled
	 * 400: Invalid driver or configuration
	 * 401: Authentication required
	 * 403: Administrator not delegated for this connection scope
	 * 404: External signing is not enabled
	 * 409: Integration conflict
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[OpenAPI(tags: ['system'])]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/signing/integrations', requirements: ['apiVersion' => '(v1)'])]
	public function create(string $driverId, string $name, array $configuration, #[\SensitiveParameter] array $secrets = [], string $scopeType = 'system', string $scopeId = ''): DataResponse {
		if (($denied = $this->authorizeScope($scopeType, $scopeId)) !== null) {
			return $denied;
		}

		return $this->execute(function () use ($driverId, $name, $configuration, $secrets, $scopeType, $scopeId): array {
			$driver = $this->driver($driverId);
			$record = $this->administration->create($driver, $name, $configuration, $secrets, $scopeType, $scopeId);
			return $this->describeWithDriver($driver, $record->id);
		}, Http::STATUS_CREATED);
	}

	/**
	 * Inspect a configured integration without exposing credentials
	 *
	 * @param string $id Opaque integration identifier
	 * @return DataResponse<Http::STATUS_OK, LibresignExternalIntegrationResponse, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_CONFLICT|Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: Integration metadata and configured secret field indicators
	 * 400: Invalid integration request
	 * 401: Authentication required
	 * 403: Administrator not delegated for this connection scope
	 * 404: Integration not found or external signing not enabled
	 * 409: Integration conflict
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[OpenAPI(tags: ['system'])]
	#[ApiRoute(verb: 'GET', url: '/api/{apiVersion}/admin/signing/integrations/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function show(string $id): DataResponse {
		if (($denied = $this->authorize()) !== null) {
			return $denied;
		}

		return $this->execute(fn (): array => $this->describe($id));
	}

	/**
	 * Check the provider's publicly advertised CSC protocol, without
	 * using or revealing configured tokens or signing on behalf of users.
	 *
	 * This administrative connectivity result never grants access to a
	 * personal credential or replaces a signer-bound OAuth authorization.
	 *
	 * @param string $id Opaque configured integration identifier
	 * @return DataResponse<Http::STATUS_OK, LibresignExternalIntegrationProbeResponse, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_CONFLICT|Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: Public CSC metadata and compatibility verdict
	 * 400: Invalid configured integration
	 * 401: Authentication required
	 * 403: Administrator is not delegated for this scope
	 * 404: Integration is unavailable or outside the administrator's scope
	 * 409: Integration conflict
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 8, period: 60)]
	#[OpenAPI(tags: ['system'])]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/signing/integrations/{id}/probe', requirements: ['apiVersion' => '(v1)'])]
	public function probe(string $id): DataResponse {
		if (($denied = $this->authorize()) !== null) {
			return $denied;
		}
		return $this->execute(function () use ($id): array {
			$record = $this->administration->get($id);
			$this->assertCanManageRecord($record);
			if ($record->driverId !== CscDriver::ID || !is_string($record->configuration['baseUrl'] ?? null)) {
				throw new \InvalidArgumentException('Not a CSC signing integration');
			}
			/** @var LibresignExternalIntegrationProbeResponse $result */
			$result = $this->providerProbe->inspect($record->configuration['baseUrl']);
			return $result;
		});
	}

	/**
	 * Update integration settings and write-only secrets
	 *
	 * Secret keys omitted from the patch preserve their current ciphertext.
	 * Explicit null removes only an optional secret. Revision is mandatory.
	 *
	 * @param string $id Opaque integration identifier
	 * @param int $revision Expected revision for optimistic locking
	 * @param string|null $name Updated display name, if supplied
	 * @param array<string, string>|null $configuration Full replacement public configuration, if supplied
	 * @param array<string, string|null> $secrets Write-only secret changes
	 * @return DataResponse<Http::STATUS_OK, LibresignExternalIntegrationResponse, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_CONFLICT|Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: Integration updated
	 * 400: Invalid configuration or revision
	 * 401: Authentication required
	 * 403: Administrator not delegated for this connection scope
	 * 404: Integration not found or external signing not enabled
	 * 409: Stale revision
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[OpenAPI(tags: ['system'])]
	#[ApiRoute(verb: 'PATCH', url: '/api/{apiVersion}/admin/signing/integrations/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function update(string $id, int $revision, ?string $name = null, ?array $configuration = null, #[\SensitiveParameter] array $secrets = []): DataResponse {
		if (($denied = $this->authorize()) !== null) {
			return $denied;
		}

		return $this->execute(function () use ($id, $revision, $name, $configuration, $secrets): array {
			$this->assertRevision($revision);
			$record = $this->administration->get($id);
			$this->assertCanManageRecord($record);
			$driver = $this->driverForRecord($record);
			$this->administration->update($driver, $id, $revision, $name, $configuration, $secrets);
			return $this->describeWithDriver($driver, $id);
		});
	}

	/**
	 * Enable an integration without changing the global feature flag
	 *
	 * @param string $id Opaque integration identifier
	 * @param int $revision Expected revision for optimistic locking
	 * @return DataResponse<Http::STATUS_OK, LibresignExternalIntegrationResponse, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_CONFLICT|Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: Integration enabled
	 * 400: Invalid revision
	 * 401: Authentication required
	 * 403: Administrator not delegated for this connection scope
	 * 404: Integration not found or external signing not enabled
	 * 409: Stale revision or driver unavailable
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[OpenAPI(tags: ['system'])]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/signing/integrations/{id}/enable', requirements: ['apiVersion' => '(v1)'])]
	public function enable(string $id, int $revision): DataResponse {
		return $this->setEnabled($id, $revision, true);
	}

	/**
	 * Disable an integration without changing the global feature flag
	 *
	 * @param string $id Opaque integration identifier
	 * @param int $revision Expected revision for optimistic locking
	 * @return DataResponse<Http::STATUS_OK, LibresignExternalIntegrationResponse, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_CONFLICT|Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: Integration disabled
	 * 400: Invalid revision
	 * 401: Authentication required
	 * 403: Administrator not delegated for this connection scope
	 * 404: Integration not found or external signing not enabled
	 * 409: Stale revision
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[OpenAPI(tags: ['system'])]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/admin/signing/integrations/{id}/disable', requirements: ['apiVersion' => '(v1)'])]
	public function disable(string $id, int $revision): DataResponse {
		return $this->setEnabled($id, $revision, false);
	}

	/**
	 * Delete an integration and erase its credentials
	 *
	 * @param string $id Opaque integration identifier
	 * @param int $revision Expected revision for optimistic locking
	 * @return DataResponse<Http::STATUS_OK, LibresignExternalIntegrationDeletionResponse, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_CONFLICT|Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 *
	 * 200: Integration deleted
	 * 400: Invalid revision
	 * 401: Authentication required
	 * 403: Administrator not delegated for this connection scope
	 * 404: Integration not found or external signing not enabled
	 * 409: Stale revision
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[OpenAPI(tags: ['system'])]
	#[ApiRoute(verb: 'DELETE', url: '/api/{apiVersion}/admin/signing/integrations/{id}', requirements: ['apiVersion' => '(v1)'])]
	public function destroy(string $id, int $revision): DataResponse {
		if (($denied = $this->authorize()) !== null) {
			return $denied;
		}

		return $this->execute(function () use ($id, $revision): array {
			$this->assertRevision($revision);
			// Deletion is permitted even if the installed driver was removed.
			$this->assertCanManageRecord($this->administration->get($id));
			$this->administration->delete($id, $revision);
			return ['deleted' => true];
		});
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, LibresignExternalIntegrationResponse, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST|Http::STATUS_CONFLICT|Http::STATUS_INTERNAL_SERVER_ERROR, LibresignErrorResponse, array{}>
	 */
	private function setEnabled(string $id, int $revision, bool $enabled): DataResponse {
		if (($denied = $this->authorize()) !== null) {
			return $denied;
		}

		return $this->execute(function () use ($id, $revision, $enabled): array {
			$this->assertRevision($revision);
			$record = $this->administration->get($id);
			$this->assertCanManageRecord($record);
			$driver = $this->driverForRecord($record);
			$this->administration->setEnabled($driver, $id, $revision, $enabled);
			return $this->describeWithDriver($driver, $id);
		});
	}

	/** @return null|DataResponse<Http::STATUS_UNAUTHORIZED|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_BAD_REQUEST, LibresignErrorResponse, array{}> */
	private function authorizeScope(string $scopeType, string $scopeId): ?DataResponse {
		if (($denied = $this->authorize()) !== null) {
			return $denied;
		}
		try {
			ExternalIntegrationStore::validateScope($scopeType, $scopeId);
		} catch (\InvalidArgumentException) {
			return new DataResponse(['error' => 'Invalid scope'], Http::STATUS_BAD_REQUEST);
		}
		return $this->scopeAccess->canManage($scopeType, $scopeId)
			? null : new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
	}

	private function assertCanManageRecord(ExternalIntegrationRecord $record): void {
		// Treat a cross-scope ID exactly like a missing integration.
		if (!$this->scopeAccess->canManage($record->scopeType, $record->scopeId)) {
			throw new \OutOfBoundsException('Integration not found');
		}
	}

	/** @return null|DataResponse<Http::STATUS_UNAUTHORIZED|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND, LibresignErrorResponse, array{}> */
	private function authorize(): ?DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
		}
		if (!$this->scopeAccess->canManageAny()) {
			return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
		}
		if (!$this->experimentalFeatures->isEnabled()) {
			return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
		}
		return null;
	}

	/** @return LibresignExternalIntegrationResponse */
	private function describe(string $id): array {
		$record = $this->administration->get($id);
		$this->assertCanManageRecord($record);
		return $this->describeWithDriver($this->driverForRecord($record), $id);
	}

	/**
	 * @param array<string, mixed> $driver
	 * @return LibresignExternalIntegrationResponse
	 */
	private function describeWithDriver(array $driver, string $id): array {
		/** @var LibresignExternalIntegrationResponse $result */
		$result = $this->administration->describe($driver, $id);
		return $result;
	}

	/** @return array<string, mixed> */
	private function driverForRecord(ExternalIntegrationRecord $record): array {
		foreach ($this->catalog->listDrivers() as $driver) {
			if ($driver['id'] === $record->driverId) {
				return $driver;
			}
		}
		throw new \OutOfBoundsException('Integration driver not found');
	}

	/** @return array<string, mixed> */
	private function driver(string $id): array {
		foreach ($this->catalog->listDrivers() as $driver) {
			if ($driver['id'] === $id) {
				return $driver;
			}
		}
		throw new \InvalidArgumentException('Unsupported external signing driver');
	}

	/** @return array<string, array<string, mixed>> */
	private function driversById(): array {
		$drivers = [];
		foreach ($this->catalog->listDrivers() as $driver) {
			$drivers[$driver['id']] = $driver;
		}
		return $drivers;
	}

	private function assertRevision(int $revision): void {
		if ($revision < 1) {
			throw new \InvalidArgumentException('Invalid integration revision');
		}
	}

	/**
	 * Convert service exceptions into stable responses without reflecting
	 * credentials or exception messages to administrators.
	 *
	 * @template TData of array<string, mixed>
	 * @template TStatus of Http::STATUS_*
	 * @param callable(): TData $operation
	 * @param TStatus $status
	 * @return DataResponse<TStatus|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_CONFLICT|Http::STATUS_INTERNAL_SERVER_ERROR, TData|LibresignErrorResponse, array{}>
	 */
	private function execute(callable $operation, int $status = Http::STATUS_OK): DataResponse {
		try {
			return new DataResponse($operation(), $status);
		} catch (\OutOfBoundsException) {
			return new DataResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException) {
			return new DataResponse(['error' => 'Invalid integration request'], Http::STATUS_BAD_REQUEST);
		} catch (\DomainException) {
			return new DataResponse(['error' => 'Integration conflict'], Http::STATUS_CONFLICT);
		} catch (ExternalIntegrationConflictException) {
			return new DataResponse(['error' => 'Integration conflict'], Http::STATUS_CONFLICT);
		} catch (\Throwable $exception) {
			$this->logger->error('External signing integration request failed', ['exception' => $exception]);
			return new DataResponse(['error' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
