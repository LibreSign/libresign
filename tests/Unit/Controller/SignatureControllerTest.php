<?php

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Tests\Unit\ApiTestCase;

final class SignatureControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testHasRootCertReturnSuccess() {
		$this->createUser('username', 'password');
		$this->request
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			])
			->withPath('/signature/has-root-cert');

		$this->assertRequest();
	}
}
