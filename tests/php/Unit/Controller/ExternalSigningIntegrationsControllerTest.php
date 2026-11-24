<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\ExternalSigningIntegrationsController;
use OCA\Libresign\Service\ExperimentalFeaturesService;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Signing\ExternalIntegrationAdministration;
use OCA\Libresign\Service\Signing\ExternalIntegrationFieldValidator;
use OCA\Libresign\Service\Signing\ExternalIntegrationSecrets;
use OCA\Libresign\Service\Signing\ExternalIntegrationStore;
use OCA\Libresign\Service\Signing\ExternalSigningIntegrationScopeAccess;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscDriver;
use OCA\Libresign\Service\Signing\Provider\ExternalSigningDriverCatalog;
use OCP\AppFramework\Http;
use OCP\Group\ISubAdmin;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ICrypto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/** Direct controller tests. Persistence and concurrency are integration-tested separately. */
final class ExternalSigningIntegrationsControllerTest extends TestCase {
	private IUserSession&MockObject $session;
	private IGroupManager&MockObject $groups;
	private ExperimentalFeaturesService&MockObject $features;
	private ExternalSigningIntegrationsController $controller;
	private LoggerInterface&MockObject $logger;
	private IDBConnection&MockObject $db;

	protected function setUp(): void {
		$this->session = $this->createMock(IUserSession::class);
		$this->groups = $this->createMock(IGroupManager::class);
		$this->features = $this->createMock(ExperimentalFeaturesService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $message): string => $message);

		$this->db = $this->createMock(IDBConnection::class);
		$store = new ExternalIntegrationStore(
			$this->db,
			new ExternalIntegrationSecrets($this->createMock(ICrypto::class)),
		);
		$this->controller = new ExternalSigningIntegrationsController(
			$this->createMock(IRequest::class),
			$this->session,
			$this->groups,
			$this->features,
			new ExternalSigningDriverCatalog($this->features, new CscDriver($l10n)),
			new ExternalIntegrationAdministration($store, new ExternalIntegrationFieldValidator()),
			new ExternalSigningIntegrationScopeAccess(
				$this->createMock(PolicyService::class),
				$this->groups, $this->createMock(IUserManager::class),
				$this->session, $this->createMock(ISubAdmin::class),
			),
			$this->logger,
		);
	}

	/** @return array<string, array{string}> */
	public static function routes(): array {
		return [
			'list' => ['index'],
			'create' => ['create'],
			'read' => ['show'],
			'update' => ['update'],
			'enable' => ['enable'],
			'disable' => ['disable'],
			'delete' => ['destroy'],
		];
	}

	#[DataProvider('routes')]
	public function testAnonymousCannotUseAnyOperation(string $route): void {
		$this->session->method('getUser')->willReturn(null);
		$this->groups->expects($this->never())->method('isAdmin');
		$this->features->expects($this->never())->method('isEnabled');

		$response = $this->invokeRoute($route);
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(['error' => 'Unauthorized'], $response->getData());
	}

	#[DataProvider('routes')]
	public function testAuthenticatedNonAdminCannotUseAnyOperation(string $route): void {
		$this->authenticated(false);
		$this->features->expects($this->never())->method('isEnabled');

		$response = $this->invokeRoute($route);
		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame(['error' => 'Forbidden'], $response->getData());
	}

	#[DataProvider('routes')]
	public function testAdminWithDisabledFeatureCannotUseAnyOperation(string $route): void {
		$this->authenticated(true);
		$this->features->method('isEnabled')->willReturn(false);

		$response = $this->invokeRoute($route);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Not found'], $response->getData());
	}

	public function testSystemAdminReceivesValidationErrorForInvalidPagination(): void {
		$this->authenticated(true);
		$this->features->method('isEnabled')->willReturn(true);
		$response = $this->controller->index(-1);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Invalid integration request'], $response->getData());
	}

	public function testUnknownDriverRejectedBeforePersistence(): void {
		$this->authenticated(true);
		$this->features->method('isEnabled')->willReturn(true);
		$response = $this->controller->create(
			'unknown-v1',
			'Synthetic',
			['baseUrl' => 'https://example.org'],
			['accessToken' => 'do-not-leak-this-value'],
		);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertStringNotContainsString('do-not-leak-this-value', json_encode($response->getData(), JSON_THROW_ON_ERROR));
	}

	public function testMalformedRevisionDoesNotLeakRawIdentifier(): void {
		$this->authenticated(true);
		$this->features->method('isEnabled')->willReturn(true);
		$response = $this->controller->enable('not-a-valid-id-and-no-secret', 0);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Invalid integration request'], $response->getData());
	}

	public function testUnexpectedDatabaseFailureIsLoggedWithoutLeakingToResponse(): void {
		$this->authenticated(true);
		$this->features->method('isEnabled')->willReturn(true);
		$exception = new \RuntimeException('synthetic-private-database-detail');
		$this->db->method('getQueryBuilder')->willThrowException($exception);
		$this->logger->expects($this->once())->method('error')
			->with('External signing integration request failed', ['exception' => $exception]);
		$response = $this->controller->index();
		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame(['error' => 'Internal error'], $response->getData());
	}

	private function authenticated(bool $admin): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('test-actor');
		$this->session->method('getUser')->willReturn($user);
		$this->groups->expects($this->atLeastOnce())->method('isAdmin')->with('test-actor')->willReturn($admin);
	}

	private function invokeRoute(string $method): \OCP\AppFramework\Http\DataResponse {
		return match ($method) {
			'index' => $this->controller->index(),
			'create' => $this->controller->create('csc-v1', 'Test', ['baseUrl' => 'https://example.org']),
			'show' => $this->controller->show('unknown'),
			'update' => $this->controller->update('unknown', 1),
			'enable' => $this->controller->enable('unknown', 1),
			'disable' => $this->controller->disable('unknown', 1),
			'destroy' => $this->controller->destroy('unknown', 1),
		};
	}
}
