<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Settings\Admin;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 */
final class AdminTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private Admin $admin;
	private IInitialState&MockObject $initialState;
	private IdentifyMethodService&MockObject $identifyMethodService;
	private CertificateEngineHandler&MockObject $certificateEngineHandler;
	private IAppConfig&MockObject $appConfig;
	public function setUp(): void {
		$this->initialState = $this->createMock(IInitialState::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->certificateEngineHandler = $this->createMock(CertificateEngineHandler::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->admin = new Admin(
			$this->initialState,
			$this->identifyMethodService,
			$this->certificateEngineHandler,
			$this->appConfig,
		);
	}

	public function testGetSessionReturningAppId():void {
		$this->assertEquals($this->admin->getSection(), Application::APP_ID);
	}

	public function testGetPriority():void {
		$this->assertEquals($this->admin->getPriority(), 100);
	}

	public function testGetFormReturnObject():void {
		$this->markTestSkipped('Need to reimplement this test, stated to failure');
		$actual = $this->admin->getForm();
		$this->assertIsObject($actual);
		$this->assertInstanceOf('\OCP\AppFramework\Http\TemplateResponse', $actual);
	}
}
