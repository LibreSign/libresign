<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Handler\CfsslHandler;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CfsslHandlerTest extends TestCase {
	public function testGenerateCertificateWithInvalidData() {
		$class = new CfsslHandler();
		$this->expectErrorMessageMatches('/Could not resolve host/');
		$class->generateCertificate();
	}

	public function testSetNonExististingProperty() {
		$class = new CfsslHandler();
		$this->expectErrorMessageMatches('/Cannot set non existing property/');
		$class->setFoo();
	}

	public function testCallInvalidMethod() {
		$class = new CfsslHandler();
		$this->expectErrorMessageMatches('/Cannot set non existing property/');
		$class->fooBar();
	}
}