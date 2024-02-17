<?php

namespace OCA\Libresign\Tests\Api\Controller;

use OCA\Libresign\Tests\Api\ApiTestCase;

/**
 * @group DB
 */
final class SettingControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testHasRootCertReturnSuccess() {
		$this->createAccount('username', 'password');
		$this->request
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			])
			->withPath('/setting/has-root-cert');

		$this->assertRequest();
	}
}
