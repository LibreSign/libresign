<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Exception\EmptyCertificateException;
use OCA\Libresign\Exception\InvalidPasswordException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\OpenSslHandler;
use OCA\Libresign\Service\CertificatePolicyService;
use OCA\Libresign\Service\SerialNumberService;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\ITempManager;
use OCP\IURLGenerator;

final class OpenSslHandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IConfig $config;
	private IAppConfig $appConfig;
	private IAppDataFactory $appDataFactory;
	private IDateTimeFormatter $dateTimeFormatter;
	private ITempManager $tempManager;
	private OpenSslHandler $openSslHandler;
	protected CertificatePolicyService $certificatePolicyService;
	private SerialNumberService $serialNumberService;
	private IURLGenerator $urlGenerator;
	private string $tempDir;
	public function setUp(): void {
		$this->config = \OCP\Server::get(IConfig::class);
		$this->appConfig = \OCP\Server::get(IAppConfig::class);
		$this->appDataFactory = \OCP\Server::get(IAppDataFactory::class);
		$this->dateTimeFormatter = \OCP\Server::get(IDateTimeFormatter::class);
		$this->tempManager = \OCP\Server::get(ITempManager::class);
		$this->certificatePolicyService = \OCP\Server::get(CertificatePolicyService::class);
		$this->serialNumberService = \OCP\Server::get(SerialNumberService::class);
		$this->urlGenerator = \OCP\Server::get(IURLGenerator::class);
		$this->tempDir = $this->tempManager->getTemporaryFolder('certificate');
	}

	private function getInstance() {
		return new OpenSslHandler(
			$this->config,
			$this->appConfig,
			$this->appDataFactory,
			$this->dateTimeFormatter,
			$this->tempManager,
			$this->certificatePolicyService,
			$this->urlGenerator,
			$this->serialNumberService,
		);
	}

	public function testEmptyCertificate(): void {
		$signerInstance = $this->getInstance();

		// Test invalid password
		$this->expectException(EmptyCertificateException::class);
		$signerInstance->readCertificate('', '');
	}

	public function testInvalidPassword(): void {
		// Create root cert
		$rootInstance = $this->getInstance();
		$rootInstance->generateRootCert('Test Root CA', []);

		// Create signer cert
		$signerInstance = $this->getInstance();
		$signerInstance->setCommonName('Test User');
		$signerInstance->setHosts(['user@email.tld']);
		$signerInstance->setPassword('right password');
		$certificateContent = $signerInstance->generateCertificate();

		// Test invalid password
		$this->expectException(InvalidPasswordException::class);
		$signerInstance->readCertificate($certificateContent, 'invalid password');
	}

	public function testMaxLengthOfDistinguishedNamesWithSuccess(): void {
		// Create root cert
		$rootInstance = $this->getInstance();
		$rootInstance->generateRootCert('Test Root CA', []);

		// Create signer cert
		$signerInstance = $this->getInstance();
		$longName = str_repeat('a', 64);
		$signerInstance->setCommonName($longName);
		$signerInstance->setPassword('123456');
		$certificateContent = $signerInstance->generateCertificate();
		$parsed = $signerInstance->readCertificate($certificateContent, '123456');
		$this->assertEquals($longName, $parsed['subject']['CN']);
	}

	public function testBiggerThanMaxLengthOfDistinguishedNamesWithError(): void {
		// Create root cert
		$rootInstance = $this->getInstance();
		$rootInstance->generateRootCert('Test Root CA', []);

		// Create signer cert
		$signerInstance = $this->getInstance();
		$longName = str_repeat('a', 65);
		$signerInstance->setCommonName($longName);
		$signerInstance->setPassword('123456');
		$this->expectException(LibresignException::class);
		$signerInstance->generateCertificate();
	}

	/**
	 * @dataProvider dataReadCertificate
	 */
	public function testReadCertificate(
		string $commonName,
		string $signerName,
		array $hosts,
		string $password,
		array $csrNames,
		array $root,
	): void {
		// Create root cert
		$rootInstance = $this->getInstance();
		if (isset($root['CN'])) {
			$rootInstance->setCommonName($root['CN']);
		}
		if (isset($root['C'])) {
			$rootInstance->setCountry($root['C']);
		}
		if (isset($root['ST'])) {
			$rootInstance->setState($root['ST']);
		}
		if (isset($root['O'])) {
			$rootInstance->setOrganization($root['O']);
		}
		if (isset($root['OU'])) {
			$rootInstance->setOrganizationalUnit($root['OU']);
		}
		$rootInstance->generateRootCert($commonName, $root);

		// Create signer cert
		$signerInstance = $this->getInstance();
		$signerInstance->setHosts($hosts);
		$signerInstance->setPassword($password);
		$signerInstance->setFriendlyName($signerName);
		if (isset($csrNames['CN'])) {
			$signerInstance->setCommonName($csrNames['CN']);
		} else {
			$signerInstance->setCommonName($signerName);
			$csrNames['CN'] = $signerName; // Add to expected values for comparison
		}
		if (isset($csrNames['C'])) {
			$signerInstance->setCountry($csrNames['C']);
		}
		if (isset($csrNames['ST'])) {
			$signerInstance->setState($csrNames['ST']);
		}
		if (isset($csrNames['O'])) {
			$signerInstance->setOrganization($csrNames['O']);
		}
		if (isset($csrNames['OU'])) {
			$signerInstance->setOrganizationalUnit($csrNames['OU']);
		}
		$certificateContent = $signerInstance->generateCertificate();

		// Parse signer cert
		$parsed = $signerInstance->readCertificate($certificateContent, $password);

		// Test total elements of extracerts
		// The correct content is: cert signer, intermediate certs (if have), root cert
		$this->assertArrayHasKey('extracerts', $parsed);
		$this->assertCount(2, $parsed['extracerts']);

		// Test name
		$name = $this->csrArrayToString($csrNames);
		$this->assertEquals($parsed['name'], $name);
		$this->assertJsonStringEqualsJsonString(
			json_encode($csrNames),
			json_encode($parsed['subject'])
		);

		// Test subject
		$this->assertEquals($csrNames, $parsed['subject']);

		// Test issuer ony if was defined root distinguished names
		if (count($root) === count($parsed['issuer'])) {
			$this->assertEquals($root, $parsed['issuer']);
		}
	}

	private function csrArrayToString(array $csr): string {
		$return = '';
		foreach ($csr as $key => $value) {
			$return .= "/$key=$value";
		}
		return $return;
	}

	public static function dataReadCertificate(): array {
		return [
			[
				'common name',
				'Signer Name',
				['user@domain.tld'],
				'password',
				[
					'C' => 'CT',
					'ST' => 'Some-State',
					'O' => 'Organization Name',
				],
				[],
			],
			[
				'common name',
				'Signer Name',
				['account:test'],
				'password',
				[
					'C' => 'CT',
					'ST' => 'Some-State',
					'O' => 'Organization Name',
					'OU' => 'Organization Unit',
					'CN' => 'Common Name',
				],
				[
					'C' => 'RT',
					'ST' => 'Root-State',
					'O' => 'Root Organization Name',
					'OU' => 'Root Organization Unit',
					'CN' => 'Root Common Name',
					'UID' => 'account:test'
				],
			],
			[
				'common name',
				'Signer Name',
				['user@domain.tld'],
				'password',
				[
					'C' => 'CT',
					'ST' => 'Some-State',
					'O' => 'Organization Name',
					'OU' => 'Organization Unit',
					'CN' => 'Common Name',
				],
				[
					'C' => 'RT',
					'ST' => 'Root-State',
					'O' => 'Root Organization Name',
					'OU' => 'Root Organization Unit',
					'CN' => 'Root Common Name',
					'UID' => 'email:user@domain.tld'
				],
			],
		];
	}

	public function testSerialNumberGeneration(): void {
		$rootInstance = $this->getInstance();
		$rootInstance->generateRootCert('Test Root CA', []);

		$signerInstance = $this->getInstance();
		$signerInstance->setCommonName('Test User');
		$signerInstance->setPassword('123456');

		$certificate = $signerInstance->generateCertificate();
		$parsed = $signerInstance->readCertificate($certificate, '123456');

		$this->assertArrayHasKey('serialNumber', $parsed, 'Certificate should have serialNumber field');
		$this->assertArrayHasKey('serialNumberHex', $parsed, 'Certificate should have serialNumberHex field');
		$this->assertNotNull($parsed['serialNumber'], 'Serial number should not be null');
		$this->assertNotNull($parsed['serialNumberHex'], 'Serial number hex should not be null');

		$this->assertNotEquals('0', $parsed['serialNumber'], 'Serial number should not be zero');
		$this->assertNotEquals('00', $parsed['serialNumberHex'], 'Serial number hex should not be zero');

		$serialInt = (int)$parsed['serialNumber'];
		$this->assertGreaterThanOrEqual(1000000, $serialInt, 'Serial number should be >= 1000000');
		$this->assertLessThanOrEqual(2147483647, $serialInt, 'Serial number should be <= 2147483647');

		$this->assertIsNumeric($parsed['serialNumber'], 'Serial number should be numeric');
		$this->assertMatchesRegularExpression('/^[0-9A-Fa-f]+$/', $parsed['serialNumberHex'], 'Serial number hex should contain only hex characters');
	}

	public function testUniqueSerialNumbers(): void {
		$rootInstance = $this->getInstance();
		$rootInstance->generateRootCert('Test Root CA', []);

		$serialNumbers = [];
		$numCertificates = 3;

		for ($i = 0; $i < $numCertificates; $i++) {
			$signerInstance = $this->getInstance();
			$signerInstance->setCommonName("Test Certificate $i");
			$signerInstance->setPassword('123456');
			$certificateContent = $signerInstance->generateCertificate();
			$parsed = $signerInstance->readCertificate($certificateContent, '123456');

			$serialNumber = $parsed['serialNumber'];

			$this->assertNotEquals('0', $serialNumber, "Certificate $i should not have serial number 0");

			$this->assertNotContains($serialNumber, $serialNumbers, "Certificate $i should have unique serial number");

			$serialNumbers[] = $serialNumber;
		}

		$this->assertCount($numCertificates, array_unique($serialNumbers), 'All serial numbers should be unique');
	}
}
