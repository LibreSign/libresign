<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Settings\Admin;
use OCP\AppFramework\Services\IInitialState;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 */
final class AdminTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private Admin $admin;
	/** @var IInitialState|MockObject */
	private $initialState;
	/** @var IdentifyMethodService|MockObject */
	private $identifyMethodService;
	public function setUp(): void {
		$this->initialState = $this->createMock(IInitialState::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->admin = new Admin(
			$this->initialState,
			$this->identifyMethodService
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
