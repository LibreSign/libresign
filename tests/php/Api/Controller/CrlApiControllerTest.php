<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Api\Controller;

use OCA\Libresign\Tests\Api\ApiTestCase;

/**
 * @group DB
 */
class CrlApiControllerTest extends ApiTestCase {
	public function setUp(): void {
		parent::setUp();
		$this->setupCertificateEngine();
	}

	private function setupCertificateEngine(): void {
		/** @var \OCA\Libresign\Service\CaIdentifierService */
		$caIdentifierService = \OCP\Server::get(\OCA\Libresign\Service\CaIdentifierService::class);
		$caIdentifierService->generateCaId('openssl');

		$factory = \OCP\Server::get(\OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory::class);
		$engine = $factory->getEngine();
		$engine->generateRootCert('Test Root CA', []);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testListCrlEntriesWithoutFilters(): void {
		$this->createAccount('username', 'password', 'admin');

		$this->request
			->withMethod('GET')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
			])
			->withPath('/api/v1/crl/list');

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);

		$this->assertArrayHasKey('ocs', $body);
		$this->assertArrayHasKey('data', $body['ocs']);
		$this->assertArrayHasKey('data', $body['ocs']['data']);
		$this->assertArrayHasKey('total', $body['ocs']['data']);
		$this->assertArrayHasKey('page', $body['ocs']['data']);
		$this->assertArrayHasKey('length', $body['ocs']['data']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testListCrlEntriesWithPagination(): void {
		$this->createAccount('username', 'password', 'admin');

		$this->request
			->withMethod('GET')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
			])
			->withPath('/api/v1/crl/list')
			->withQuery([
				'page' => 1,
				'length' => 10,
			]);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);

		$this->assertArrayHasKey('ocs', $body);
		$this->assertEquals(1, $body['ocs']['data']['page']);
		$this->assertEquals(10, $body['ocs']['data']['length']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testListCrlEntriesWithFilters(): void {
		$this->createAccount('username', 'password', 'admin');

		$this->request
			->withMethod('GET')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
			])
			->withPath('/api/v1/crl/list')
			->withQuery([
				'status' => 'issued',
				'engine' => 'openssl',
			]);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);

		$this->assertArrayHasKey('ocs', $body);
		$this->assertArrayHasKey('data', $body['ocs']['data']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testListCrlEntriesWithSorting(): void {
		$this->createAccount('username', 'password', 'admin');

		$this->request
			->withMethod('GET')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
			])
			->withPath('/api/v1/crl/list')
			->withQuery([
				'sortBy' => 'issued_at',
				'sortOrder' => 'DESC',
			]);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);

		$this->assertArrayHasKey('ocs', $body);
		$this->assertArrayHasKey('data', $body['ocs']['data']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testRevokeCertificateWithEmptySerialNumber(): void {
		$this->createAccount('username', 'password', 'admin');

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json',
			])
			->withPath('/api/v1/crl/revoke')
			->withRequestBody([
				'serialNumber' => '',
			])
			->assertResponseCode(400);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);

		$this->assertFalse($body['ocs']['data']['success']);
		$this->assertEquals('Serial number is required', $body['ocs']['data']['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testRevokeCertificateWithInvalidReasonCode(): void {
		$this->createAccount('username', 'password', 'admin');

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json',
			])
			->withPath('/api/v1/crl/revoke')
			->withRequestBody([
				'serialNumber' => '123456',
				'reasonCode' => 99, // Invalid code
			])
			->assertResponseCode(400);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);

		$this->assertFalse($body['ocs']['data']['success']);
		$this->assertStringContainsString('Invalid reason code', $body['ocs']['data']['message']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testRevokeCertificateWithValidData(): void {
		$this->createAccount('username', 'password', 'admin');

		// First, we need to create a certificate to revoke
		// This test assumes there's a valid certificate in the system
		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json',
			])
			->withPath('/api/v1/crl/revoke')
			->withRequestBody([
				'serialNumber' => '999999',
				'reasonCode' => 0, // Unspecified
				'reasonText' => 'Test revocation',
			])
			->assertResponseCode(404); // Certificate doesn't exist, so we expect 404

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);

		$this->assertFalse($body['ocs']['data']['success']);
		$this->assertArrayHasKey('message', $body['ocs']['data']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testRevokeCertificateWithValidReasonCodes(): void {
		$this->createAccount('username', 'password', 'admin');

		// Test all valid CRL reason codes (0-10, excluding 7)
		$validReasonCodes = [0, 1, 2, 3, 4, 5, 6, 8, 9, 10];

		foreach ($validReasonCodes as $reasonCode) {
			$this->request
				->withMethod('POST')
				->withRequestHeader([
					'Authorization' => 'Basic ' . base64_encode('username:password'),
					'Content-Type' => 'application/json',
				])
				->withPath('/api/v1/crl/revoke')
				->withRequestBody([
					'serialNumber' => '999999' . $reasonCode,
					'reasonCode' => $reasonCode,
					'reasonText' => 'Test revocation with reason code ' . $reasonCode,
				])
				->assertResponseCode(404); // Certificate doesn't exist

			$response = $this->assertRequest();
			$body = json_decode($response->getBody()->getContents(), true);

			// Even though the certificate doesn't exist, the request should be valid
			// according to the OpenAPI schema
			$this->assertArrayHasKey('ocs', $body);
			$this->assertArrayHasKey('data', $body['ocs']);
		}
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testRevokeCertificateWithoutReasonCode(): void {
		$this->createAccount('username', 'password', 'admin');

		$this->request
			->withMethod('POST')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
				'Content-Type' => 'application/json',
			])
			->withPath('/api/v1/crl/revoke')
			->withRequestBody([
				'serialNumber' => '123456',
			])
			->assertResponseCode(404); // Certificate doesn't exist, but request is valid

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);

		$this->assertArrayHasKey('ocs', $body);
		$this->assertArrayHasKey('data', $body['ocs']);
		$this->assertArrayHasKey('success', $body['ocs']['data']);
		$this->assertArrayHasKey('message', $body['ocs']['data']);
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testListCrlEntriesReturnsCorrectStructure(): void {
		$this->createAccount('username', 'password', 'admin');

		$this->request
			->withMethod('GET')
			->withRequestHeader([
				'Authorization' => 'Basic ' . base64_encode('username:password'),
			])
			->withPath('/api/v1/crl/list')
			->withQuery([
				'page' => 1,
				'length' => 5,
			]);

		$response = $this->assertRequest();
		$body = json_decode($response->getBody()->getContents(), true);

		// Validate the OpenAPI schema requirements
		$this->assertArrayHasKey('ocs', $body);
		$this->assertArrayHasKey('meta', $body['ocs']);
		$this->assertArrayHasKey('data', $body['ocs']);

		$data = $body['ocs']['data'];
		$this->assertArrayHasKey('data', $data);
		$this->assertArrayHasKey('total', $data);
		$this->assertArrayHasKey('page', $data);
		$this->assertArrayHasKey('length', $data);

		// Validate data types
		$this->assertIsArray($data['data']);
		$this->assertIsInt($data['total']);
		$this->assertIsInt($data['page']);
		$this->assertIsInt($data['length']);
	}
}
