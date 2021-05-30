<?php

namespace OCA\Libresign\Tests\Unit\Controller;

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;
use OCA\Libresign\Tests\Unit\ApiTestCase;
use org\bovigo\vfs\vfsStream;

final class AdminControllerTest extends ApiTestCase {
	/** @var MockWebServer */
	protected static $server;

	public static function setUpBeforeClass(): void {
		self::$server = new MockWebServer;
		self::$server->start();
	}

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
	public function testGenerateCertificate() {
		// Mock data
		$this->createUser('username', 'password');
		vfsStream::setup('home');
		self::$server->setResponseOfPath('/api/v1/cfssl/health', new Response(
			'{"success":true,"result":{"healthy":true},"errors":[],"messages":[]}'
		));

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
				'cfsslUri' => self::$server->getServerRoot() . '/api/v1/cfssl/',
				'configPath' => 'vfs://home/'
			]);

		// Make and test request mach with schema
		$this->assertRequest();

		// Test result of endpoint
		$csrServerJson = file_get_contents('vfs://home/csr_server.json');
		$this->assertJsonStringEqualsJsonString(
			'{"CN":"CommonName","key":{"algo":"rsa","size":2048},"names":[{"C":"Brazil","O":"Organization","OU":"organizationUnit","CN":"CommonName"}]}',
			$csrServerJson
		);

		$configServerJson = file_get_contents('vfs://home/config_server.json');
		$this->assertJson($configServerJson);
	}

	public static function tearDownAfterClass(): void {
		self::$server->stop();
	}
}
