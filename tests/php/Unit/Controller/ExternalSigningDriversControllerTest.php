<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\ExternalSigningDriversController;
use OCA\Libresign\Service\ExperimentalFeaturesService;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Signing\External\IntegrationScopeAccess;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscDriver;
use OCA\Libresign\Service\Signing\Provider\ExternalSigningDriverCatalog;
use OCP\AppFramework\Http;
use OCP\Group\ISubAdmin;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ExternalSigningDriversControllerTest extends TestCase {
	private IUserSession&MockObject $session;
	private IGroupManager&MockObject $groups;
	private ExperimentalFeaturesService&MockObject $feature;
	private ExternalSigningDriversController $controller;

	protected function setUp(): void {
		$this->session = $this->createMock(IUserSession::class);
		$this->groups = $this->createMock(IGroupManager::class);
		$this->feature = $this->createMock(ExperimentalFeaturesService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $label): string => $label);

		$this->controller = new ExternalSigningDriversController(
			$this->createMock(IRequest::class),
			$this->session,
			$this->groups,
			new ExternalSigningDriverCatalog($this->feature, new CscDriver($l10n)),
			new IntegrationScopeAccess(
				$this->createMock(PolicyService::class), $this->groups,
				$this->createMock(IUserManager::class), $this->session,
				$this->createMock(ISubAdmin::class),
			),
		);
	}

	public function testAnonymousCallerIsDeniedBeforeFlagEvaluation(): void {
		$this->session->method('getUser')->willReturn(null);
		$this->feature->expects($this->never())->method('isEnabled');

		$response = $this->controller->index();
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(['error' => 'Unauthorized'], $response->getData());
	}

	public function testAuthenticatedNonAdminIsDeniedBeforeFlagEvaluation(): void {
		$this->authenticatedUser(false);
		$this->feature->expects($this->never())->method('isEnabled');

		$response = $this->controller->index();
		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame(['error' => 'Forbidden'], $response->getData());
	}

	public function testDisabledFlagHidesExperimentalCatalogFromAdmin(): void {
		$this->authenticatedUser(true);
		$this->feature->method('isEnabled')->willReturn(false);

		$response = $this->controller->index();
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Not found'], $response->getData());
	}

	public function testEnabledCatalogResponseUsesOneConsistentFlagSnapshot(): void {
		$this->authenticatedUser(true);
		$this->feature->method('isEnabled')->willReturnOnConsecutiveCalls(true, false);

		$response = $this->controller->index();
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertCount(1, $response->getData()['drivers']);
	}

	public function testAdminCanReadOnlyDriverMetadataWithoutSecrets(): void {
		$this->authenticatedUser(true);
		$this->feature->method('isEnabled')->willReturn(true);

		$response = $this->controller->index();
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('csc-v1', $response->getData()['drivers'][0]['id']);
		$this->assertSame(['baseUrl', 'accessToken'], array_column($response->getData()['drivers'][0]['fields'], 'key'));
		$this->assertArrayNotHasKey('value', $response->getData()['drivers'][0]['fields'][1]);
		$this->assertArrayNotHasKey('instanceId', $response->getData()['drivers'][0]);
		$this->assertArrayNotHasKey('config', $response->getData()['drivers'][0]);
	}

	private function authenticatedUser(bool $admin): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('actor');
		$this->session->method('getUser')->willReturn($user);
		$this->groups->expects($this->atLeastOnce())->method('isAdmin')->with('actor')->willReturn($admin);
	}
}
