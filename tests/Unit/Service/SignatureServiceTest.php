<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\SignatureService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class SignatureServiceTest extends TestCase {
	public function testHasRootCertReturningTrue() {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->will($this->returnValue('authKeyValue'));
		$service = new SignatureService(
			$config
		);
		$this->assertTrue($service->hasRootCert());
	}

	public function testHasRootCertReturningFalse() {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->will($this->returnValue(null));
		$service = new SignatureService(
			$config
		);
		$this->assertFalse($service->hasRootCert());
	}
}
