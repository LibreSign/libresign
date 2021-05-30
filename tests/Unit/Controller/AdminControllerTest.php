<?php

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Tests\Unit\ApiTestCase;

final class AdminControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testLoadCertificate() {
		$this->createUser('username', 'password');
		$this->request
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password')
			])
			->withPath('/admin/certificate');

		$this->assertRequest();
	}
}
