<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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

		// Setup CA certificate for CRL tests
		$this->setupCertificateEngine();

		$this->request = new \OCA\Libresign\Tests\Api\ApiRequester();
	}

	private function setupCertificateEngine(): void {
		// Clean up any existing CRL data that might have invalid reason codes
		$crlMapper = \OCP\Server::get(\OCA\Libresign\Db\CrlMapper::class);
		$connection = \OC::$server->getDatabaseConnection();
		$connection->executeStatement('DELETE FROM oc_libresign_crl');

		// Create a root certificate for testing
		$factory = \OCP\Server::get(\OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory::class);
		$engine = $factory->getEngine();
		$engine->generateRootCert('Test Root CA', []);
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
