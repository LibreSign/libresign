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
		$this->appConfig = $this->getMockAppConfig();
		$this->appDataFactory = \OCP\Server::get(IAppDataFactory::class);
		$this->dateTimeFormatter = \OCP\Server::get(IDateTimeFormatter::class);
		$this->tempManager = \OCP\Server::get(ITempManager::class);
		$this->certificatePolicyService = \OCP\Server::get(CertificatePolicyService::class);
		$this->urlGenerator = \OCP\Server::get(IURLGenerator::class);
		$this->caIdentifierService = \OCP\Server::get(CaIdentifierService::class);
		$this->logger = \OCP\Server::get(LoggerInterface::class);

		$this->appConfig->deleteKey(Application::APP_ID, 'certificate_engine');
		$this->appConfig->deleteKey(Application::APP_ID, 'identify_methods');
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
}
