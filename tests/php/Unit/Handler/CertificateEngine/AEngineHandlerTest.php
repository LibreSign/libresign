<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\OpenSslHandler;
use OCA\Libresign\Service\CaIdentifierService;
use OCA\Libresign\Service\CertificatePolicyService;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\ITempManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

final class AEngineHandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IConfig $config;
	private IAppConfig $appConfig;
	private IAppDataFactory $appDataFactory;
	private IDateTimeFormatter $dateTimeFormatter;
	private ITempManager $tempManager;
	private CertificatePolicyService $certificatePolicyService;
	private IURLGenerator $urlGenerator;
	private CaIdentifierService $caIdentifierService;
	private LoggerInterface $logger;

	public function setUp(): void {
		$this->config = \OCP\Server::get(IConfig::class);
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->appDataFactory = \OCP\Server::get(IAppDataFactory::class);
		$this->dateTimeFormatter = \OCP\Server::get(IDateTimeFormatter::class);
		$this->tempManager = \OCP\Server::get(ITempManager::class);
		$this->certificatePolicyService = \OCP\Server::get(CertificatePolicyService::class);
		$this->urlGenerator = \OCP\Server::get(IURLGenerator::class);
		$this->caIdentifierService = \OCP\Server::get(CaIdentifierService::class);
		$this->logger = \OCP\Server::get(LoggerInterface::class);
	}

	private function getInstance(): OpenSslHandler {
		return new OpenSslHandler(
			$this->config,
			$this->appConfig,
			$this->appDataFactory,
			$this->dateTimeFormatter,
			$this->tempManager,
			$this->certificatePolicyService,
			$this->urlGenerator,
			\OCP\Server::get(\OCA\Libresign\Service\SerialNumberService::class),
			$this->caIdentifierService,
			$this->logger,
			\OCP\Server::get(\OCA\Libresign\Db\CrlMapper::class),
		);
	}

	#[DataProvider('dataProviderEngines')]
	public function testSetEngineSavesCertificateEngineConfig(string $engine): void {
		$instance = $this->getInstance();

		$instance->setEngine($engine);

		$savedEngine = $this->appConfig->getValueString(Application::APP_ID, 'certificate_engine');
		$this->assertEquals($engine, $savedEngine);
	}

	#[DataProvider('dataProviderEngines')]
	public function testSetEngineUpdatesInternalProperty(string $engine): void {
		$instance = $this->getInstance();

		$instance->setEngine($engine);

		$this->assertEquals($engine, $instance->getEngine());
	}

	#[DataProvider('dataProviderIdentifyMethodsNoneEngine')]
	public function testSetEngineConfiguresIdentifyMethodsForNoneEngine(
		string $fromEngine,
		?array $initialIdentifyMethods,
		string $description,
	): void {
		$instance = $this->getInstance();

		if ($fromEngine !== '') {
			$this->appConfig->setValueString(Application::APP_ID, 'certificate_engine', $fromEngine);
		}
		if ($initialIdentifyMethods !== null) {
			$this->appConfig->setValueArray(Application::APP_ID, 'identify_methods', $initialIdentifyMethods);
		}

		$instance->setEngine('none');

		$savedIdentifyMethods = $this->appConfig->getValueArray(Application::APP_ID, 'identify_methods');
		$expected = [[
			'name' => 'account',
			'enabled' => true,
			'mandatory' => true,
		]];

		$this->assertEquals(
			$expected,
			$savedIdentifyMethods,
			"identify_methods should be restricted for none engine: $description"
		);
	}

	#[DataProvider('dataProviderIdentifyMethodsOtherEngines')]
	public function testSetEnginePreservesIdentifyMethodsForOtherEngines(
		string $fromEngine,
		string $toEngine,
		?array $initialIdentifyMethods,
		string $description,
	): void {
		$instance = $this->getInstance();

		if ($fromEngine !== '') {
			$this->appConfig->setValueString(Application::APP_ID, 'certificate_engine', $fromEngine);
		}
		if ($initialIdentifyMethods !== null) {
			$this->appConfig->setValueArray(Application::APP_ID, 'identify_methods', $initialIdentifyMethods);
		}

		$instance->setEngine($toEngine);

		$savedIdentifyMethods = $this->appConfig->getValueArray(Application::APP_ID, 'identify_methods');
		$this->assertEquals(
			$initialIdentifyMethods ?? [],
			$savedIdentifyMethods,
			"identify_methods should not be modified: $description"
		);
	}

	public static function dataProviderEngines(): array {
		return [
			'openssl engine' => ['openssl'],
			'cfssl engine' => ['cfssl'],
			'none engine' => ['none'],
		];
	}

	public static function dataProviderIdentifyMethodsNoneEngine(): array {
		$noneConfig = [[
			'name' => 'account',
			'enabled' => true,
			'mandatory' => true,
			'signatureMethods' => [
				'clickToSign' => ['enabled' => false, 'name' => 'clickToSign'],
				'emailToken' => ['enabled' => false, 'name' => 'emailToken'],
				'password' => ['enabled' => true, 'name' => 'password'],
			],
		]];

		$fullConfig = [
			[
				'name' => 'account',
				'enabled' => true,
				'mandatory' => false,
				'signatureMethods' => [
					'clickToSign' => ['enabled' => true, 'name' => 'clickToSign'],
					'emailToken' => ['enabled' => true, 'name' => 'emailToken'],
					'password' => ['enabled' => true, 'name' => 'password'],
				],
			],
			[
				'name' => 'email',
				'enabled' => true,
				'mandatory' => false,
				'signatureMethods' => [
					'clickToSign' => ['enabled' => true, 'name' => 'clickToSign'],
					'emailToken' => ['enabled' => true, 'name' => 'emailToken'],
					'password' => ['enabled' => true, 'name' => 'password'],
				],
			],
		];

		return [
			'First time setting to none' => ['', null, 'no previous config'],
			'From openssl to none' => ['openssl', $fullConfig, 'with full config'],
			'From cfssl to none' => ['cfssl', $fullConfig, 'with full config'],
			'Keeping none' => ['none', $noneConfig, 'already restricted'],
		];
	}

	public static function dataProviderIdentifyMethodsOtherEngines(): array {
		$noneConfig = [[
			'name' => 'account',
			'enabled' => true,
			'mandatory' => true,
			'signatureMethods' => [
				'clickToSign' => ['enabled' => false, 'name' => 'clickToSign'],
				'emailToken' => ['enabled' => false, 'name' => 'emailToken'],
				'password' => ['enabled' => true, 'name' => 'password'],
			],
		]];

		$fullConfig = [
			[
				'name' => 'account',
				'enabled' => true,
				'mandatory' => false,
				'signatureMethods' => [
					'clickToSign' => ['enabled' => true],
					'emailToken' => ['enabled' => true],
					'password' => ['enabled' => true],
				],
			],
			[
				'name' => 'email',
				'enabled' => true,
				'mandatory' => false,
				'signatureMethods' => [
					'clickToSign' => ['enabled' => true],
					'emailToken' => ['enabled' => true],
					'password' => ['enabled' => true],
				],
			],
		];

		return [
			'From none to openssl' => ['none', 'openssl', $noneConfig, 'should preserve restricted config'],
			'From none to cfssl' => ['none', 'cfssl', $noneConfig, 'should preserve restricted config'],
			'From openssl to cfssl' => ['openssl', 'cfssl', $fullConfig, 'should preserve full config'],
			'From cfssl to openssl' => ['cfssl', 'openssl', $fullConfig, 'should preserve full config'],
			'First time openssl' => ['', 'openssl', null, 'no previous config'],
			'First time cfssl' => ['', 'cfssl', null, 'no previous config'],
		];
	}

	#[DataProvider('dataProviderToArray')]
	public function testToArrayReturnsExpectedStructure(
		bool $isSetupOk,
		array $certificateData,
		array $expectedKeys,
		string $description,
	): void {
		$instance = $this->getInstance();

		foreach ($certificateData as $setter => $value) {
			$method = 'set' . ucfirst($setter);
			if (method_exists($instance, $method)) {
				$instance->$method($value);
			}
		}

		if (!$isSetupOk) {
			$this->appConfig->deleteKey(Application::APP_ID, 'config_path');
		}

		$result = $instance->toArray();

		foreach ($expectedKeys as $key) {
			$this->assertArrayHasKey($key, $result, "toArray() should contain '$key': $description");
		}

		$this->assertIsBool($result['generated'], "generated should be boolean: $description");
	}

	#[DataProvider('dataProviderToArrayConfigPath')]
	public function testToArrayFiltersConfigPathWhenNotGenerated(
		bool $certificateGenerated,
		string $expectedConfigPath,
		string $description,
	): void {
		$instance = $this->getInstance();

		$tempPath = $this->tempManager->getTemporaryFolder('test-config');
		$instance->setConfigPath($tempPath);

		if ($certificateGenerated) {
			file_put_contents($tempPath . DIRECTORY_SEPARATOR . 'ca.pem', 'fake-cert');
			file_put_contents($tempPath . DIRECTORY_SEPARATOR . 'ca-key.pem', 'fake-key');
		}

		$result = $instance->toArray();

		if ($expectedConfigPath === '') {
			$this->assertEmpty($result['configPath'], "configPath should be empty: $description");
		} else {
			$this->assertNotEmpty($result['configPath'], "configPath should not be empty: $description");
		}

		$this->assertEquals($certificateGenerated, $result['generated'], "generated flag: $description");
	}

	#[DataProvider('dataProviderToArrayCaIdFiltering')]
	public function testToArrayFiltersCaIdFromOrganizationalUnitWhenNotGenerated(
		bool $certificateGenerated,
		array $organizationalUnits,
		array $expectedOuValues,
		string $description,
	): void {
		$instance = $this->getInstance();

		$tempPath = $this->tempManager->getTemporaryFolder('test-config');
		$instance->setConfigPath($tempPath);
		$instance->setOrganizationalUnit($organizationalUnits);
		$instance->setCountry('BR');

		if ($certificateGenerated) {
			file_put_contents($tempPath . DIRECTORY_SEPARATOR . 'ca.pem', 'fake-cert');
			file_put_contents($tempPath . DIRECTORY_SEPARATOR . 'ca-key.pem', 'fake-key');
		}

		$result = $instance->toArray();

		$ouFound = null;
		foreach ($result['rootCert']['names'] as $name) {
			if ($name['id'] === 'OU') {
				$ouFound = $name['value'];
				break;
			}
		}

		if (!empty($expectedOuValues)) {
			$this->assertNotNull($ouFound, "OU should be present in names array: $description");
			$this->assertEquals(
				$expectedOuValues,
				$ouFound,
				"OrganizationalUnit filtering: $description"
			);
		} else {
			$this->assertNull($ouFound, "OU should not be present when filtered to empty: $description");
		}
	}

	public static function dataProviderToArray(): array {
		return [
			'Basic structure with minimal data' => [
				false,
				['commonName' => 'Test CA'],
				['configPath', 'generated', 'rootCert', 'policySection'],
				'minimal certificate data',
			],
			'Complete certificate data' => [
				false,
				[
					'commonName' => 'LibreSign CA',
					'country' => 'BR',
					'state' => 'Santa Catarina',
					'locality' => 'Florianópolis',
					'organization' => 'LibreCode',
					'organizationalUnit' => ['Engineering', 'Security'],
				],
				['configPath', 'generated', 'rootCert', 'policySection'],
				'full certificate data',
			],
		];
	}

	public static function dataProviderToArrayConfigPath(): array {
		return [
			'Certificate not generated' => [
				false,
				'',
				'configPath should be empty when certificate not generated',
			],
			'Certificate generated' => [
				true,
				'/path/to/config',
				'configPath should be set when certificate is generated',
			],
		];
	}

	public static function dataProviderToArrayCaIdFiltering(): array {
		return [
			'OU without CA ID - not generated' => [
				false,
				['Engineering', 'Security'],
				['Engineering', 'Security'],
				'normal OU values should pass through when not generated',
			],
			'OU without CA ID - generated' => [
				true,
				['Engineering', 'Security'],
				['Engineering', 'Security'],
				'normal OU values should pass through when generated',
			],
			'OU with CA ID - not generated (filtered)' => [
				false,
				['libresign-ca-id:abc123_g:1_e:openssl', 'Engineering', 'Security'],
				['Engineering', 'Security'],
				'CA ID should be filtered when certificate not generated',
			],
			'OU with CA ID - generated (kept)' => [
				true,
				['libresign-ca-id:abc123_g:1_e:openssl', 'Engineering', 'Security'],
				['libresign-ca-id:abc123_g:1_e:openssl', 'Engineering', 'Security'],
				'CA ID should be kept when certificate is generated',
			],
			'OU with only CA ID - not generated' => [
				false,
				['libresign-ca-id:abc123_g:1_e:openssl'],
				[],
				'OU should be empty when only CA ID and not generated',
			],
			'OU with only CA ID - generated' => [
				true,
				['libresign-ca-id:abc123_g:1_e:openssl'],
				['libresign-ca-id:abc123_g:1_e:openssl'],
				'OU with only CA ID should be kept when generated',
			],
		];
	}
}
