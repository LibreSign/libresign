<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Api\Controller;

use OCA\Libresign\Tests\Api\ApiTestCase;

/**
 * @group DB
 */
final class AdminControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testLoadCertificate():void {
		$this->createAccount('admintest', 'password', 'admin');
		$this->request
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('admintest:password')
			])
			->withPath('/api/v1/admin/certificate');

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testGenerateCertificateWithFailure():void {
		// Configure request
		$this->createAccount('admintest', 'password', 'admin');
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('admintest:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/api/v1/admin/certificate/openssl')
			->withRequestBody([
				'rootCert' => [
					'commonName' => 'CommonName',
					'names' => [
						'Invalid' => ['value' => 'BR'],
					],
				],
				'configPath' => ''
			])
			->assertResponseCode(401);

		// Make and test request mach with schema
		$this->assertRequest();
	}

}
