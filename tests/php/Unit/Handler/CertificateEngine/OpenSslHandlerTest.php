<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Db\CrlMapper;
use OCA\Libresign\Exception\EmptyCertificateException;
use OCA\Libresign\Exception\InvalidPasswordException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\OpenSslHandler;
use OCA\Libresign\Service\CaIdentifierService;
use OCA\Libresign\Service\CertificatePolicyService;
use OCA\Libresign\Service\SerialNumberService;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\ITempManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

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
	private CaIdentifierService $caIdentifierService;
	private CrlMapper $crlMapper;
	private LoggerInterface $logger;
	private string $tempDir;
	public function setUp(): void {
		$this->config = \OCP\Server::get(IConfig::class);
		$this->appConfig = $this->getMockAppConfig();
		$this->appDataFactory = \OCP\Server::get(IAppDataFactory::class);
		$this->dateTimeFormatter = \OCP\Server::get(IDateTimeFormatter::class);
		$this->tempManager = \OCP\Server::get(ITempManager::class);
		$this->certificatePolicyService = \OCP\Server::get(CertificatePolicyService::class);
		$this->serialNumberService = \OCP\Server::get(SerialNumberService::class);
		$this->urlGenerator = \OCP\Server::get(IURLGenerator::class);
		$this->tempDir = $this->tempManager->getTemporaryFolder('certificate');
		$this->caIdentifierService = \OCP\Server::get(CaIdentifierService::class);
		$this->crlMapper = \OCP\Server::get(CrlMapper::class);
		$this->logger = \OCP\Server::get(LoggerInterface::class);
		$this->caIdentifierService->generateCaId('openssl');
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
			$this->caIdentifierService,
			$this->logger,
			$this->crlMapper,
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
			$rootInstance->setOrganizationalUnit([$root['OU']]);
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
			$signerInstance->setOrganizationalUnit([$csrNames['OU']]);
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

	private function parseDnString(string $dn): array {
		$result = [];
		// Parse DN string like "/C=US/ST=State/O=Org/CN=Name"
		$parts = explode('/', trim($dn, '/'));
		foreach ($parts as $part) {
			if (strpos($part, '=') !== false) {
				[$key, $value] = explode('=', $part, 2);
				$result[$key] = $value;
			}
		}
		return $result;
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
		$this->assertLessThanOrEqual(PHP_INT_MAX, $serialInt, 'Serial number should be <= PHP_INT_MAX');

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

	public function testRealCertificateRevocationInCrl(): void {
		$this->caIdentifierService->generateCaId('openssl');

		$rootInstance = $this->getInstance();
		$rootInstance->generateRootCert('Test Root CA', []);

		$signerInstance = $this->getInstance();
		$signerInstance->setCommonName('Test User for Revocation');
		$signerInstance->setPassword('123456');
		$certificateContent = $signerInstance->generateCertificate();

		$parsed = $signerInstance->readCertificate($certificateContent, '123456');

		$revokedCert = new \OCA\Libresign\Db\Crl();
		$revokedCert->setSerialNumber($parsed['serialNumberHex']);
		$revokedCert->setRevokedAt(new \DateTime('2025-01-01 12:00:00'));

		$configPath = $rootInstance->getCurrentConfigPath();
		$pkiDirName = basename($configPath);
		preg_match('/^([^_]+)_(\d+)_(.+)$/', $pkiDirName, $matches);

		$crlDer = $rootInstance->generateCrlDer([$revokedCert], $matches[1], (int)$matches[2], 1);

		$tempCrlFile = $this->tempManager->getTemporaryFile('.crl');
		file_put_contents($tempCrlFile, $crlDer);

		exec(sprintf('openssl crl -in %s -inform DER -text -noout', escapeshellarg($tempCrlFile)), $output);

		$crlText = implode("\n", $output);

		$this->assertMatchesRegularExpression('/Serial Number: 0*' . preg_quote($parsed['serialNumberHex'], '/') . '/', $crlText);

		unlink($tempCrlFile);
	}

	public static function revokedCertificatesProvider(): array {
		return [
			'empty CRL (no revoked certificates)' => [
				'certificates' => [],
			],
			'single revoked certificate' => [
				'certificates' => [
					['revokedAt' => '2025-01-01 12:00:00'],
				],
			],
			'two revoked certificates' => [
				'certificates' => [
					['revokedAt' => '2025-01-01 12:00:00'],
					['revokedAt' => '2025-01-02 15:30:00'],
				],
			],
			'three revoked certificates' => [
				'certificates' => [
					['revokedAt' => '2025-01-01 12:00:00'],
					['revokedAt' => '2025-01-02 15:30:00'],
					['revokedAt' => '2025-01-03 18:45:00'],
				],
			],
			'five revoked certificates' => [
				'certificates' => [
					['revokedAt' => '2025-01-01 12:00:00'],
					['revokedAt' => '2025-01-02 15:30:00'],
					['revokedAt' => '2025-01-03 18:45:00'],
					['revokedAt' => '2025-01-04 09:15:00'],
					['revokedAt' => '2025-01-05 14:20:00'],
				],
			],
		];
	}

	/**
	 * @dataProvider revokedCertificatesProvider
	 */
	public function testGenerateCrlDerWithRevokedCertificates(array $certificates): void {
		$this->caIdentifierService->generateCaId('openssl');

		$rootInstance = $this->getInstance();
		$rootInstance->generateRootCert('Test Root CA', []);

		$revokedCertificates = [];
		$serialNumbers = [];

		foreach ($certificates as $certData) {
			$serialNumber = bin2hex(random_bytes(10));
			$serialNumbers[] = $serialNumber;

			$revokedCert = new \OCA\Libresign\Db\Crl();
			$revokedCert->setSerialNumber($serialNumber);
			$revokedCert->setRevokedAt(new \DateTime($certData['revokedAt']));
			$revokedCertificates[] = $revokedCert;
		}

		$configPath = $rootInstance->getCurrentConfigPath();
		$this->assertDirectoryExists($configPath);
		$this->assertFileExists($configPath . DIRECTORY_SEPARATOR . 'ca.pem');
		$this->assertFileExists($configPath . DIRECTORY_SEPARATOR . 'ca-key.pem');

		$pkiDirName = basename($configPath);
		$this->assertMatchesRegularExpression('/^[^_]+_\d+_.+$/', $pkiDirName);
		preg_match('/^([^_]+)_(\d+)_(.+)$/', $pkiDirName, $matches);
		$instanceId = $matches[1];
		$generation = (int)$matches[2];
		$crlNumber = 42;

		$crlDer = $rootInstance->generateCrlDer($revokedCertificates, $instanceId, $generation, $crlNumber);

		$this->assertNotEmpty($crlDer);
		$this->assertIsString($crlDer);

		$tempCrlFile = $this->tempManager->getTemporaryFile('.crl');
		try {
			file_put_contents($tempCrlFile, $crlDer);

			$crlTextCmd = sprintf(
				'openssl crl -in %s -inform DER -text -noout',
				escapeshellarg($tempCrlFile)
			);
			exec($crlTextCmd, $output, $exitCode);

			$this->assertEquals(0, $exitCode, 'OpenSSL should successfully parse the CRL');

			$crlText = implode("\n", $output);

			$this->assertStringContainsString('Certificate Revocation List (CRL)', $crlText, 'Should be a valid CRL');
			$this->assertStringContainsString('Issuer:', $crlText, 'CRL should contain Issuer');
			$this->assertStringContainsString('Last Update:', $crlText, 'CRL should contain Last Update date');
			$this->assertStringContainsString('Next Update:', $crlText, 'CRL should contain Next Update date');
			$this->assertStringContainsString('Signature Algorithm:', $crlText, 'CRL should contain signature algorithm');

			if (empty($certificates)) {
				$this->assertStringContainsString('No Revoked Certificates', $crlText, 'Empty CRL should show "No Revoked Certificates"');
			} else {
				$this->assertStringNotContainsString('No Revoked Certificates', $crlText, 'CRL with revocations should not show "No Revoked Certificates"');
				$this->assertStringContainsString('Revoked Certificates:', $crlText, 'CRL should have Revoked Certificates section');

				$this->assertMatchesRegularExpression('/X509v3 CRL Number:\s+(\d+)/i', $crlText, 'CRL Number extension should be present');
				preg_match('/X509v3 CRL Number:\s+(\d+)/i', $crlText, $crlMatches);
				$actualCrlNumber = (int)$crlMatches[1];
				$this->assertEquals($crlNumber, $actualCrlNumber, 'CRL Number should match the provided value');

				foreach ($serialNumbers as $serialNumber) {
					$normalizedSerial = ltrim(strtoupper($serialNumber), '0') ?: '0';
					$this->assertStringContainsString($normalizedSerial, $crlText, "Serial number $serialNumber (normalized: $normalizedSerial) should appear in CRL");
				}
			}

			$caCertPath = $configPath . DIRECTORY_SEPARATOR . 'ca.pem';
			$verifyCmd = sprintf(
				'openssl crl -in %s -inform DER -CAfile %s -noout 2>&1',
				escapeshellarg($tempCrlFile),
				escapeshellarg($caCertPath)
			);
			exec($verifyCmd, $verifyOutput, $verifyExitCode);
			$verifyResult = implode("\n", $verifyOutput);

			$this->assertEquals(0, $verifyExitCode, 'CRL signature verification should succeed. Output: ' . $verifyResult);
			$this->assertStringContainsString('verify OK', $verifyResult, 'CRL signature should be valid');

		} finally {
			if (file_exists($tempCrlFile)) {
				unlink($tempCrlFile);
			}
		}
	}

	public static function dataCrlSerialNumberNormalization(): array {
		return [
			'Serial with leading zeros (20 chars)' => [
				'serialNumber' => '00e7a0b277a1008f5fe3',
				'expectedInCrl' => 'E7A0B277A1008F5FE3'
			],
			'Serial without leading zeros (20 chars)' => [
				'serialNumber' => 'e7a0b277a1008f5fe3',
				'expectedInCrl' => 'E7A0B277A1008F5FE3'
			],
			'Serial with multiple leading zeros (8 chars)' => [
				'serialNumber' => '00000001',
				'expectedInCrl' => '01' // OpenSSL maintains at least 2 digits
			],
			'Serial number 1 (single char)' => [
				'serialNumber' => '1',
				'expectedInCrl' => '01' // OpenSSL maintains at least 2 digits
			],
			'Serial 0xFF with leading zeros' => [
				'serialNumber' => '000000FF',
				'expectedInCrl' => 'FF'
			],
			'Long serial with leading zeros (40 chars)' => [
				'serialNumber' => '0023EE47B385E2D71D5B30A09AE34887D5AF595694',
				'expectedInCrl' => '23EE47B385E2D71D5B30A09AE34887D5AF595694'
			],
			'Long serial without leading zeros (40 chars)' => [
				'serialNumber' => '23EE47B385E2D71D5B30A09AE34887D5AF595694',
				'expectedInCrl' => '23EE47B385E2D71D5B30A09AE34887D5AF595694'
			],
			'Very long serial with leading zeros (42 chars)' => [
				'serialNumber' => '00A1B2C3D4E5F6789012345678901234567890ABCD',
				'expectedInCrl' => 'A1B2C3D4E5F6789012345678901234567890ABCD'
			],
			'Serial starting with letter, no zeros' => [
				'serialNumber' => 'FEDCBA9876543210',
				'expectedInCrl' => 'FEDCBA9876543210'
			],
			'Serial starting with letter, with zeros' => [
				'serialNumber' => '00FEDCBA9876543210',
				'expectedInCrl' => 'FEDCBA9876543210'
			],
			'Lowercase long serial with zeros' => [
				'serialNumber' => '00abcdef1234567890abcdef1234567890',
				'expectedInCrl' => 'ABCDEF1234567890ABCDEF1234567890'
			],
			'Mixed case long serial' => [
				'serialNumber' => '00AaBbCcDdEeFf1122334455',
				'expectedInCrl' => 'AABBCCDDEEFF1122334455'
			],
		];
	}

	#[DataProvider('dataCrlSerialNumberNormalization')]
	public function testCrlSerialNumberNormalization(string $serialNumber, string $expectedInCrl): void {
		$this->caIdentifierService->generateCaId('openssl');

		$rootInstance = $this->getInstance();
		$rootInstance->generateRootCert('Test Root CA', []);

		$revokedCert = new \OCA\Libresign\Db\Crl();
		$revokedCert->setSerialNumber($serialNumber);
		$revokedCert->setRevokedAt(new \DateTime('2025-01-01 12:00:00'));

		$configPath = $rootInstance->getCurrentConfigPath();
		$pkiDirName = basename($configPath);
		preg_match('/^([^_]+)_(\d+)_(.+)$/', $pkiDirName, $matches);
		$instanceId = $matches[1];
		$generation = (int)$matches[2];
		$crlNumber = 42;

		$crlDer = $rootInstance->generateCrlDer([$revokedCert], $instanceId, $generation, $crlNumber);

		$this->assertNotEmpty($crlDer, "CRL should be generated for serial: {$serialNumber}");

		$tempCrlFile = $this->tempManager->getTemporaryFile('.crl');
		try {
			file_put_contents($tempCrlFile, $crlDer);

			$crlTextCmd = sprintf(
				'openssl crl -in %s -inform DER -text -noout',
				escapeshellarg($tempCrlFile)
			);
			exec($crlTextCmd, $output, $exitCode);

			$this->assertEquals(0, $exitCode, "OpenSSL should parse CRL for serial: {$serialNumber}");

			$crlText = implode("\n", $output);

			$this->assertStringContainsString(
				$expectedInCrl,
				$crlText,
				"Expected serial '{$expectedInCrl}' should appear in CRL (input: '{$serialNumber}')"
			);

			$this->assertStringContainsString(
				'Serial Number: ' . $expectedInCrl,
				$crlText,
				"Serial should appear with 'Serial Number:' prefix (input: '{$serialNumber}')"
			);

		} finally {
			if (file_exists($tempCrlFile)) {
				unlink($tempCrlFile);
			}
		}
	}

	public function testValidateRootCertificateNotFound(): void {
		$handler = $this->getInstance();

		$configPath = $handler->getCurrentConfigPath();
		$rootCertPath = $configPath . DIRECTORY_SEPARATOR . 'ca.pem';

		if (file_exists($rootCertPath)) {
			unlink($rootCertPath);
		}

		$handler->validateRootCertificate();

		$this->assertFileDoesNotExist($rootCertPath);
	}

	#[DataProvider('expiryScenarioProvider')]
	public function testRootCertificateExpiryScenarios(
		int $caExpiryDays,
		int $leafExpiryDays,
		int $ageInDays,
		bool $needsRenewal,
		string $description,
	): void {
		$this->appConfig->setValueInt('libresign', 'ca_expiry_in_days', $caExpiryDays);
		$this->appConfig->setValueInt('libresign', 'expiry_in_days', $leafExpiryDays);

		$handler = $this->getInstance();

		$handler->generateRootCert('Test Root CA for ' . $description, []);

		if ($ageInDays > 0) {
			$this->simulateCertificateAging($handler, $ageInDays);
		}

		$handler->validateRootCertificate();

		$configPath = $handler->getCurrentConfigPath();
		$rootCertPath = $configPath . DIRECTORY_SEPARATOR . 'ca.pem';
		$this->assertFileExists($rootCertPath);

		$certContent = file_get_contents($rootCertPath);
		$certInfo = openssl_x509_parse(openssl_x509_read($certContent));

		$secondsPerDay = 60 * 60 * 24;
		$remainingDays = (int)ceil(($certInfo['validTo_time_t'] - time()) / $secondsPerDay);

		if ($needsRenewal) {
			$this->assertLessThanOrEqual($leafExpiryDays, $remainingDays,
				"Certificate should need renewal: remaining days ({$remainingDays}) <= leaf expiry days ({$leafExpiryDays})");
		} else {
			$this->assertGreaterThan($leafExpiryDays, $remainingDays,
				"Certificate should NOT need renewal: remaining days ({$remainingDays}) > leaf expiry days ({$leafExpiryDays})");
		}
	}

	public static function expiryScenarioProvider(): array {
		return [
			'newly_created' => [
				'caExpiryDays' => 3650,      // 10 years
				'leafExpiryDays' => 365,     // 1 year
				'ageInDays' => 0,            // No aging
				'needsRenewal' => false,
				'description' => 'Newly created certificate with 10 years validity',
			],
			'two_years_remaining' => [
				'caExpiryDays' => 3650,
				'leafExpiryDays' => 365,
				'ageInDays' => 2920,         // ~8 years passed, 2 years remaining
				'needsRenewal' => false,
				'description' => 'Certificate with 2 years remaining (no renewal needed)',
			],
			'at_renewal_threshold' => [
				'caExpiryDays' => 3650,
				'leafExpiryDays' => 365,
				'ageInDays' => 3285,         // Exactly 365 days remaining
				'needsRenewal' => true,
				'description' => 'Certificate at renewal threshold (365 days = leaf validity)',
			],
			'below_renewal_threshold' => [
				'caExpiryDays' => 3650,
				'leafExpiryDays' => 365,
				'ageInDays' => 3380,         // 270 days remaining
				'needsRenewal' => true,
				'description' => 'Certificate needing renewal (270 days < 365 days)',
			],
			'short_ca_valid' => [
				'caExpiryDays' => 730,       // 2 years
				'leafExpiryDays' => 90,      // 3 months
				'ageInDays' => 0,
				'needsRenewal' => false,
				'description' => 'Short-lived CA (2 years) with short-lived leaf (90 days)',
			],
			'short_ca_needs_renewal' => [
				'caExpiryDays' => 730,
				'leafExpiryDays' => 90,
				'ageInDays' => 650,          // 80 days remaining
				'needsRenewal' => true,
				'description' => 'Short-lived CA needing renewal (80 days < 90 days)',
			],
			'very_close_to_expiry' => [
				'caExpiryDays' => 3650,
				'leafExpiryDays' => 365,
				'ageInDays' => 3620,         // 30 days remaining
				'needsRenewal' => true,
				'description' => 'Certificate very close to expiry (30 days < 365 days)',
			],
			'almost_expired' => [
				'caExpiryDays' => 365,
				'leafExpiryDays' => 90,
				'ageInDays' => 357,          // 8 days remaining
				'needsRenewal' => true,
				'description' => 'Certificate almost expired (8 days < 90 days)',
			],
			'long_leaf_short_ca' => [
				'caExpiryDays' => 730,       // 2 years CA
				'leafExpiryDays' => 365,     // 1 year leaf
				'ageInDays' => 0,
				'needsRenewal' => false,
				'description' => 'Long-lived leaf (365 days) with 2-year CA (730 days)',
			],
			'ca_shorter_than_leaf' => [
				'caExpiryDays' => 180,       // 6 months CA
				'leafExpiryDays' => 365,     // 1 year leaf
				'ageInDays' => 0,
				'needsRenewal' => true,  // CA will expire before leaf validity period
				'description' => 'Edge case: CA (180 days) expires before leaf validity (365 days)',
			],
		];
	}

	private function simulateCertificateAging(OpenSslHandler $handler, int $ageInDays): void {
		$configPath = $handler->getCurrentConfigPath();
		$certPath = $configPath . '/ca.pem';
		$keyPath = $configPath . '/ca-key.pem';

		$cert = openssl_x509_read(file_get_contents($certPath));
		$certData = openssl_x509_parse($cert);
		$this->assertIsArray($certData, 'Failed to parse certificate');
		$this->assertArrayHasKey('subject', $certData, 'Certificate must have subject field');

		$privateKey = openssl_pkey_get_private(file_get_contents($keyPath));

		$dn = is_array($certData['subject']) ? $certData['subject'] : $this->parseDnString($certData['subject']);
		$this->assertIsArray($dn, 'DN must be an array');

		$secondsPerDay = 60 * 60 * 24;
		$originalValidity = (int)ceil(($certData['validTo_time_t'] - $certData['validFrom_time_t']) / $secondsPerDay);
		$newValidity = $originalValidity - $ageInDays;

		$this->assertGreaterThan(0, $newValidity, "Cannot age certificate by {$ageInDays} days - would be expired");

		$csr = openssl_csr_new($dn, $privateKey, ['digest_alg' => 'sha256']);
		$x509 = openssl_csr_sign($csr, null, $privateKey, $newValidity, [
			'digest_alg' => 'sha256',
			'config' => $configPath . '/openssl.cnf',
			'x509_extensions' => 'v3_ca',
		], random_int(1000000, PHP_INT_MAX));

		openssl_x509_export($x509, $newCert);
		file_put_contents($certPath, $newCert);
	}

	#[DataProvider('configureCheckExpiryProvider')]
	public function testConfigureCheckExpiryWarnings(
		int $caExpiryDays,
		int $leafExpiryDays,
		int $ageInDays,
		?string $expectedLevel,
		string $description,
	): void {
		$this->appConfig->setValueInt('libresign', 'ca_expiry_in_days', $caExpiryDays);
		$this->appConfig->setValueInt('libresign', 'expiry_in_days', $leafExpiryDays);

		$handler = $this->getInstance();
		$handler->generateRootCert('Test Root CA for ' . $description, []);

		if ($ageInDays > 0) {
			$this->simulateCertificateAging($handler, $ageInDays);
		}

		$checks = $handler->configureCheck();
		$this->assertIsArray($checks);

		$expiryCheck = null;
		foreach ($checks as $check) {
			$checkArray = $check->jsonSerialize();
			if (isset($checkArray['message']) && str_contains($checkArray['message'], 'expires')) {
				$expiryCheck = $checkArray;
				break;
			}
			if (isset($checkArray['message']) && str_contains($checkArray['message'], 'expired')) {
				$expiryCheck = $checkArray;
				break;
			}
		}

		if ($expectedLevel === null) {
			$this->assertNull($expiryCheck, 'No expiry warning expected for: ' . $description);
		} else {
			$this->assertNotNull($expiryCheck, 'Expiry warning expected for: ' . $description);
			$this->assertEquals($expectedLevel, $expiryCheck['status'], 'Expected level: ' . $expectedLevel . ' for: ' . $description);
		}
	}

	public static function configureCheckExpiryProvider(): array {
		return [
			'newly_created_no_warning' => [
				'caExpiryDays' => 3650,
				'leafExpiryDays' => 365,
				'ageInDays' => 0,
				'expectedLevel' => null,
				'description' => 'Newly created - no warning',
			],
			'two_years_remaining_no_warning' => [
				'caExpiryDays' => 3650,
				'leafExpiryDays' => 365,
				'ageInDays' => 2920,
				'expectedLevel' => null,
				'description' => '2 years remaining - no warning',
			],
			'at_leaf_validity_info' => [
				'caExpiryDays' => 3650,
				'leafExpiryDays' => 365,
				'ageInDays' => 3285,
				'expectedLevel' => 'info',
				'description' => 'At leaf validity threshold - info',
			],
			'below_leaf_validity_info' => [
				'caExpiryDays' => 3650,
				'leafExpiryDays' => 365,
				'ageInDays' => 3380,
				'expectedLevel' => 'info',
				'description' => 'Below leaf validity - info',
			],
			'29_days_warning' => [
				'caExpiryDays' => 3650,
				'leafExpiryDays' => 365,
				'ageInDays' => 3621,
				'expectedLevel' => 'error',
				'description' => '29 days remaining - error',
			],
			'7_days_error' => [
				'caExpiryDays' => 365,
				'leafExpiryDays' => 90,
				'ageInDays' => 358,
				'expectedLevel' => 'error',
				'description' => '7 days remaining - error',
			],
			'1_day_error' => [
				'caExpiryDays' => 365,
				'leafExpiryDays' => 90,
				'ageInDays' => 364,
				'expectedLevel' => 'error',
				'description' => '1 day remaining - error',
			],
		];
	}
}
