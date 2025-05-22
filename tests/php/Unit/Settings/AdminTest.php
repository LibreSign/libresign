<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Service\CertificatePolicyService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
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
	private CertificateEngineFactory&MockObject $certificateEngineFactory;
	private CertificatePolicyService&MockObject $certificatePolicyService;
	private IAppConfig&MockObject $appConfig;
	private SignatureTextService&MockObject $signatureTextService;
	private SignatureBackgroundService&MockObject $signatureBackgroundService;
	public function setUp(): void {
		$this->initialState = $this->createMock(IInitialState::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->certificatePolicyService = $this->createMock(CertificatePolicyService::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->signatureTextService = $this->createMock(SignatureTextService::class);
		$this->signatureBackgroundService = $this->createMock(SignatureBackgroundService::class);
		$this->admin = new Admin(
			$this->initialState,
			$this->identifyMethodService,
			$this->certificateEngineFactory,
			$this->certificatePolicyService,
			$this->appConfig,
			$this->signatureTextService,
			$this->signatureBackgroundService,
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
		$this->assertInstanceOf(\OCP\AppFramework\Http\TemplateResponse::class, $actual);
	}
}
