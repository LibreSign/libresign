<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Settings\Admin;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 */
final class AdminTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private Admin $admin;
	private IInitialState|MockObject $initialState;
	private IdentifyMethodService|MockObject $identifyMethodService;
	private IConfig|MockObject $config;
	public function setUp(): void {
		$this->initialState = $this->createMock(IInitialState::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->config = $this->createMock(IConfig::class);
		$this->admin = new Admin(
			$this->initialState,
			$this->identifyMethodService,
			$this->config
		);
	}

	public function testGetSessionReturningAppId() {
		$this->assertEquals($this->admin->getSection(), Application::APP_ID);
	}

	public function testGetPriority() {
		$this->assertEquals($this->admin->getPriority(), 100);
	}

	public function testGetFormReturnObject() {
		$actual = $this->admin->getForm();
		$this->assertIsObject($actual);
		$this->assertInstanceOf('\OCP\AppFramework\Http\TemplateResponse', $actual);
	}
}
