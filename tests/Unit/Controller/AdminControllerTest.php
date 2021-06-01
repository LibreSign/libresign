<?php

namespace OCA\Libresign\Tests\Unit\Controller;

use donatj\MockWebServer\Response;
use OCA\Libresign\Tests\lib\AppConfigOverwrite;
use OCA\Libresign\Tests\Unit\ApiTestCase;
use org\bovigo\vfs\vfsStream;

/**
 * @group DB
 */
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

	/**
	 * @runInSeparateProcess
	 */
	public function testGenerateCertificateWithSuccess() {
		// Mock data
		$this->createUser('username', 'password');
		vfsStream::setup('home');
		self::$server->setResponseOfPath('/api/v1/cfssl/health', new Response(
			'{"success":true,"result":{"healthy":true},"errors":[],"messages":[]}'
		));
		$cfsslConfig = [
			'commonName' => 'CommonName',
			'country' => 'Brazil',
			'organization' => 'Organization',
			'organizationUnit' => 'organizationUnit',
			'cfsslUri' => self::$server->getServerRoot() . '/api/v1/cfssl/',
			'configPath' => 'vfs://home/'
		];
		\OC::$server->registerService(\OC\AppConfig::class, function () use ($cfsslConfig) {
			return new AppConfigOverwrite(\OC::$server->get(\OC\DB\Connection::class), [
				'libresign' => $cfsslConfig
			]);
		});

		// Configure request
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/admin/certificate')
			->withRequestBody($cfsslConfig);

		// Make and test request mach with schema
		$this->assertRequest();

		// Test if settings has been saved
		foreach ($cfsslConfig as $key => $value) {
			$this->assertEquals(\OC::$server->get(\OC\AllConfig::class)->getAppValue('libresign', $key), $value);
		}

		// Test result of endpoint
		$csrServerJson = file_get_contents('vfs://home/csr_server.json');
		$this->assertJsonStringEqualsJsonString(
			'{"CN":"CommonName","key":{"algo":"rsa","size":2048},"names":[{"C":"Brazil","O":"Organization","OU":"organizationUnit","CN":"CommonName"}]}',
			$csrServerJson
		);

		$configServerJson = file_get_contents('vfs://home/config_server.json');
		$this->assertJson($configServerJson);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testGenerateCertificateWithFailure() {
		// Mock data
		$this->createUser('username', 'password');

		// Configure request
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json'
			])
			->withPath('/admin/certificate')
			->withRequestBody([
				'commonName' => 'CommonName',
				'country' => 'Brazil',
				'organization' => 'Organization',
				'organizationUnit' => 'organizationUnit',
				'cfsslUri' => '',
				'configPath' => ''
			])
			->assertResponseCode(401);

		// Make and test request mach with schema
		$this->assertRequest();
	}

	public static function tearDownAfterClass(): void {
		self::$server->stop();
	}
}
