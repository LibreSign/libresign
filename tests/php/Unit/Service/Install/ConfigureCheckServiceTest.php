<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Mock extension_loaded in the ConfigureCheckService namespace to control its behavior in tests
 */
namespace OCA\Libresign\Service\Install;

function extension_loaded(string $name): bool {
	return \OCA\Libresign\Tests\Unit\Service\Install\ConfigureCheckServiceTest::$mockExtensionLoaded[$name] ?? \extension_loaded($name);
}

namespace OCA\Libresign\Tests\Unit\Service\Install;

use OC\AppConfig;
use OC\SystemConfig;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\SignEngine\JSignPdfHandler;
use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCA\Libresign\Helper\JavaHelper;
use OCA\Libresign\Service\Install\ConfigureCheckService;
use OCA\Libresign\Service\Install\SignSetupService;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class ConfigureCheckServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	public static array $mockExtensionLoaded = [];

	private IAppConfig $appConfig;
	private SystemConfig&MockObject $systemConfig;
	private AppConfig&MockObject $ocAppConfig;
	private IAppManager&MockObject $appManager;
	private IURLGenerator&MockObject $urlGenerator;
	private JSignPdfHandler&MockObject $jSignPdfHandler;
	private CertificateEngineFactory&MockObject $certificateEngineFactory;
	private SignSetupService&MockObject $signSetupService;
	private LoggerInterface&MockObject $logger;
	private JavaHelper&MockObject $javaHelper;

	public function setUp(): void {
		self::$mockExtensionLoaded = [];
		$this->appConfig = $this->getMockAppConfig();
		$this->systemConfig = $this->createMock(SystemConfig::class);
		$this->ocAppConfig = $this->createMock(AppConfig::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->jSignPdfHandler = $this->createMock(JSignPdfHandler::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->signSetupService = $this->createMock(SignSetupService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->javaHelper = $this->createMock(JavaHelper::class);
	}

	public function tearDown(): void {
		self::$mockExtensionLoaded = [];
	}

	private function getInstance(): ConfigureCheckService {
		return new ConfigureCheckService(
			$this->appConfig,
			$this->systemConfig,
			$this->ocAppConfig,
			$this->appManager,
			$this->urlGenerator,
			$this->jSignPdfHandler,
			$this->certificateEngineFactory,
			$this->signSetupService,
			$this->logger,
			$this->javaHelper,
		);
	}

	public static function providerCheckExtension(): array {
		return [
			'extension not loaded' => ['imagick', false, 1],
			'extension loaded' => ['imagick', true, 0],
		];
	}

	#[DataProvider('providerCheckExtension')]
	public function testCheckExtension(string $extension, bool $extensionLoaded, int $expectedCount): void {
		self::$mockExtensionLoaded[$extension] = $extensionLoaded;

		$service = $this->getInstance();
		$result = $service->checkImagick();

		$this->assertIsArray($result);
		$this->assertCount($expectedCount, $result);

		if ($expectedCount > 0) {
			$this->assertInstanceOf(ConfigureCheckHelper::class, $result[0]);
			$this->assertEquals($extension, $result[0]->getResource());
		}
	}
}
