<?php

namespace OCA\Libresign\Tests\Api\Controller;

use donatj\MockWebServer\Response;
use OCA\Libresign\Tests\Api\ApiTestCase;
use org\bovigo\vfs\vfsStream;

/**
 * @group DB
 */
final class AdminControllerTest extends ApiTestCase {
	/**
	 * @runInSeparateProcess
	 */
	public function testLoadCertificate() {
		$this->request
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('admin:admin')
			])
			->withPath('/admin/certificate');

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testGenerateCertificateWithSuccess() {
		$this->markTestSkipped('Need to reimplement this test, stated to failure after add multiple certificate engine');
		// Mock data
		vfsStream::setup('home');
		self::$server->setResponseOfPath('/api/v1/cfssl/health', new Response(
			'{"success":true,"result":{"healthy":true},"errors":[],"messages":[]}'
		));
		$cfsslConfig = [
			'rootCert' => json_encode([
				'commonName' => 'LibreCode',
				'names' => [
					'C' => ['value' => 'BR'],
					'ST' => ['value' => 'RJ'],
					'L' => ['value' => 'Rio de Janeiro'],
					'O' => ['value' => 'LibreCode Coop'],
					'OU' => ['value' => 'LibreSign'],
				],
			]),
			'cfsslUri' => self::$server->getServerRoot() . '/api/v1/cfssl/',
			'configPath' => 'vfs://home/'
		];
		$this->mockConfig(['libresign' => $cfsslConfig]);
		$cfsslConfig['rootCert'] = json_decode($cfsslConfig['rootCert'], true);

		// Configure request
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('admin:admin'),
				'Content-Type' => 'application/json'
			])
			->withPath('/admin/certificate/cfssl')
			->withRequestBody($cfsslConfig);

		// Make and test request mach with schema
		$this->assertRequest();

		// Test if settings has been saved
		$this->assertEquals(\OC::$server->get(\OC\AllConfig::class)->getAppValue('libresign', 'cfsslUri'), $cfsslConfig['cfsslUri']);
		$this->assertEquals(\OC::$server->get(\OC\AllConfig::class)->getAppValue('libresign', 'configPath'), $cfsslConfig['configPath']);
		$rootCert = \OC::$server->get(\OC\AllConfig::class)->getAppValue('libresign', 'rootCert');
		$this->assertEqualsCanonicalizing(
			$cfsslConfig['rootCert'],
			json_decode($rootCert, true)
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
	public function testGenerateCertificateWithFailure() {
		// Configure request
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('admin:admin'),
				'Content-Type' => 'application/json'
			])
			->withPath('/admin/certificate/openssl')
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
