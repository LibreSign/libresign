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
		$this->appConfig = \OCP\Server::get(IAppConfig::class);
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
}
