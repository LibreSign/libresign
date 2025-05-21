<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Api\Controller;

use bovigo\vfs\vfsStream;
use donatj\MockWebServer\Response;
use OCA\Libresign\AppInfo\Application;
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
	public function testGenerateCertificateWithSuccess():void {
		$this->markTestSkipped('Need to reimplement this test, stated to failure after add multiple certificate engine');
		// Mock data
		$this->createAccount('admintest', 'password', 'admin');
		vfsStream::setup('home');
		self::$server->setResponseOfPath('/api/v1/cfssl/health', new Response(
			'{"success":true,"result":{"healthy":true},"errors":[],"messages":[]}'
		));
		$appConfig = $this->getMockAppConfig();
		$appConfig->setValueArray(Application::APP_ID, 'rootCert', [
			'commonName' => 'LibreCode',
			'names' => [
				'C' => ['value' => 'BR'],
				'ST' => ['value' => 'RJ'],
				'L' => ['value' => 'Rio de Janeiro'],
				'O' => ['value' => 'LibreCode Coop'],
				'OU' => ['value' => 'LibreSign'],
			],
		]);
		$appConfig->setValueString(Application::APP_ID, 'cfssl_uri', self::$server->getServerRoot() . '/api/v1/cfssl/');
		$appConfig->setValueString(Application::APP_ID, 'config_path', 'vfs://home/');
		$cfsslConfig['rootCert'] = json_decode((string)$cfsslConfig['rootCert'], true);

		// Configure request
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('admintest:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/api/v1/admin/certificate/cfssl')
			->withRequestBody($cfsslConfig);

		// Make and test request mach with schema
		$this->assertRequest();

		// Test if settings has been saved
		$this->assertEquals(\OCP\Server::get(\OC\AllConfig::class)->getAppValue('libresign', 'cfssl_uri'), $cfsslConfig['cfsslUri']);
		$this->assertEquals(\OCP\Server::get(\OC\AllConfig::class)->getAppValue('libresign', 'config_path'), $cfsslConfig['configPath']);
		$rootCert = \OCP\Server::get(\OC\AllConfig::class)->getAppValue('libresign', 'rootCert');
		$this->assertEqualsCanonicalizing(
			$cfsslConfig['rootCert'],
			json_decode((string)$rootCert, true)
		);

		// Test result of endpoint
		$csrServerJson = file_get_contents('vfs://home/csr_server.json');
		$this->assertJsonStringEqualsJsonString(
			'{"CN":"LibreCode","key":{"algo":"rsa","size":2048},"names":[{"C":"BR","ST":"RJ","L":"Rio de Janeiro","O":"LibreCode Coop","OU":"LibreSign"}]}',
			$csrServerJson
		);

		$configServerJson = file_get_contents('vfs://home/config_server.json');
		$this->assertJson($configServerJson);
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
