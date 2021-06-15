<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Settings\Admin;

/**
 * @internal
 */
final class AdminTest extends \OCA\Libresign\Tests\Unit\TestCase {
	public function testGetSessionReturningAppId() {
		$admin = new Admin();
		$this->assertEquals($admin->getSection(), Application::APP_ID);
	}

	public function testGetPriority() {
		$admin = new Admin();
		$this->assertEquals($admin->getPriority(), 100);
	}

	public function testGetFormReturnObject() {
		$admin = new Admin();
		$actual = $admin->getForm();
		$this->assertIsObject($actual);
		$this->assertInstanceOf('\OCP\AppFramework\Http\TemplateResponse', $actual);
	}
}
