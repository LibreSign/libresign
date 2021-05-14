<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Settings\Admin;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class AdminTest extends TestCase {
	public function testGetSessionReturningAppId() {
		$admin = new Admin();
		$this->assertEquals($admin->getSection(), 'libresign');
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