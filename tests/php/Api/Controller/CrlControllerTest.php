<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Api\Controller;

use ByJG\ApiTools\OpenApi\OpenApiSchema;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Tests\Api\ApiTestCase;

/**
 * @group DB
 */
class CrlControllerTest extends ApiTestCase {
	private const VALID_CERT_SERIAL = '123456';

	public function setUp(): void {
		$data = json_decode(file_get_contents('openapi-full.json'), true);
		$data['servers'][] = ['url' => '/index.php/apps/libresign'];
		$data = $this->removeBasePath($data);
		/** @var OpenApiSchema */
		$schema = \ByJG\ApiTools\Base\Schema::getInstance($data);
		$this->setSchema($schema);

		// Optmize loading time
		$systemConfig = \OCP\Server::get(\OC\SystemConfig::class);
		$systemConfig->setValue('auth.bruteforce.protection.enabled', false);

		// Reset settings
		$this->getMockAppConfig()->setValueBool(Application::APP_ID, 'identification_documents', false);

		$this->request = new \OCA\Libresign\Tests\Api\ApiRequester();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testGetRevocationListReturnsValidResponse(): void {
		$this->request
			->withMethod('GET')
			->withPath('/crl');

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testCheckCertificateStatusWithValidSerial(): void {
		$this->request
			->withMethod('GET')
			->withPath('/crl/check/' . self::VALID_CERT_SERIAL);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testCheckCertificateStatusWithInvalidSerial(): void {
		$this->request
			->withMethod('GET')
			->withPath('/crl/check/invalid')
			->assertResponseCode(400);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testCheckCertificateStatusWithNegativeSerial(): void {
		$this->request
			->withMethod('GET')
			->withPath('/crl/check/-123')
			->assertResponseCode(400);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testCheckCertificateStatusWithZeroSerial(): void {
		$this->request
			->withMethod('GET')
			->withPath('/crl/check/0')
			->assertResponseCode(400);

		$this->assertRequest();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testCrlEndpointsArePublic(): void {
		$this->request
			->withMethod('GET')
			->withPath('/crl');

		$this->assertRequest();
	}
}
