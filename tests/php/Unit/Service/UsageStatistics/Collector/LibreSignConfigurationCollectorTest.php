<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\UsageStatistics\Collector;

use OCA\Libresign\Service\UsageStatistics\Collector\LibreSignConfigurationCollector;
use OCA\Libresign\Service\UsageStatistics\Model\ReportMode;
use OCA\Libresign\Service\UsageStatistics\Model\ReportPeriod;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;

final class LibreSignConfigurationCollectorTest extends TestCase {
	private IAppConfig $appConfig;
	private string $pkiPath = '';

	public function setUp(): void {
		$this->appConfig = self::getMockAppConfigWithReset();
	}

	public function tearDown(): void {
		if ($this->pkiPath !== '') {
			array_map('unlink', glob($this->pkiPath . '/*') ?: []);
			rmdir($this->pkiPath);
		}
	}

	public function testAHistoricalReportKnowsNoConfigurationValue(): void {
		$values = $this->collector()->collect($this->period(), ReportMode::HISTORICAL);

		foreach ($values as $value) {
			$this->assertFalse($value->isAvailable());
		}
	}

	public function testDefaultsMatchWhatLibreSignRuns(): void {
		$values = $this->collector()->collect($this->period(), ReportMode::CURRENT);

		$this->assertSame('16.0.0', $values['libresign.version']->value());
		$this->assertSame('JSignPdf', $values['libresign.signature_engine']->value());
		$this->assertSame('sync', $values['libresign.signing_mode']->value());
		$this->assertSame('openssl', $values['certificate.engine']->value());
		$this->assertFalse($values['certificate.root_ca_configured']->value());
	}

	#[DataProvider('storedValuesProvider')]
	public function testStoredValuesAreNormalized(string $key, string $stored, string $metricId, string $expected): void {
		$this->appConfig->setValueString('libresign', $key, $stored);

		$values = $this->collector()->collect($this->period(), ReportMode::CURRENT);

		$this->assertSame($expected, $values[$metricId]->value());
	}

	public static function storedValuesProvider(): array {
		return [
			'native signature engine' => ['signature_engine', 'PhpNative', 'libresign.signature_engine', 'PhpNative'],
			'unknown signature engine runs JSignPdf' => ['signature_engine', 'Other', 'libresign.signature_engine', 'JSignPdf'],
			'async signing' => ['signing_mode', 'async', 'libresign.signing_mode', 'async'],
			'unknown signing mode runs sync' => ['signing_mode', 'later', 'libresign.signing_mode', 'sync'],
			'cfssl' => ['certificate_engine', 'cfssl', 'certificate.engine', 'cfssl'],
			'no certificate engine' => ['certificate_engine', 'none', 'certificate.engine', 'none'],
			'unknown certificate engine' => ['certificate_engine', 'custom', 'certificate.engine', 'other'],
		];
	}

	#[DataProvider('rootCaProvider')]
	public function testTheRootCaIsConfiguredWhenItsFilesExist(string $engine, array $files, bool $expected): void {
		$this->pkiPath = sys_get_temp_dir() . '/libresign-usage-pki-' . uniqid('', true);
		mkdir($this->pkiPath);
		foreach ($files as $file) {
			touch($this->pkiPath . '/' . $file);
		}
		$this->appConfig->setValueString('libresign', 'certificate_engine', $engine);
		$this->appConfig->setValueString('libresign', 'config_path', $this->pkiPath);

		$values = $this->collector()->collect($this->period(), ReportMode::CURRENT);

		$this->assertSame($expected, $values['certificate.root_ca_configured']->value());
	}

	public static function rootCaProvider(): array {
		return [
			'openssl with certificate and key' => ['openssl', ['ca.pem', 'ca-key.pem'], true],
			'cfssl with certificate and key' => ['cfssl', ['ca.pem', 'ca-key.pem'], true],
			'certificate without key' => ['openssl', ['ca.pem'], false],
			'no certificate engine' => ['none', ['ca.pem', 'ca-key.pem'], false],
		];
	}

	public function testAMissingPkiDirectoryIsNotCreated(): void {
		$missingPath = sys_get_temp_dir() . '/libresign-usage-missing-' . uniqid('', true);
		$this->appConfig->setValueString('libresign', 'config_path', $missingPath);

		$values = $this->collector()->collect($this->period(), ReportMode::CURRENT);

		$this->assertFalse($values['certificate.root_ca_configured']->value());
		$this->assertDirectoryDoesNotExist($missingPath);
	}

	public function testNoConfigurationDetailBecomesAMetricValue(): void {
		$this->pkiPath = sys_get_temp_dir() . '/libresign-usage-pki-' . uniqid('', true);
		mkdir($this->pkiPath);
		touch($this->pkiPath . '/ca.pem');
		touch($this->pkiPath . '/ca-key.pem');
		$this->appConfig->setValueString('libresign', 'config_path', $this->pkiPath);

		$values = $this->collector()->collect($this->period(), ReportMode::CURRENT);

		foreach ($values as $value) {
			$this->assertStringNotContainsString($this->pkiPath, (string)$value->value());
		}
	}

	private function collector(): LibreSignConfigurationCollector {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('getAppVersion')->with('libresign')->willReturn('16.0.0');
		return new LibreSignConfigurationCollector($appManager, $this->appConfig);
	}

	private function period(): ReportPeriod {
		return ReportPeriod::monthContaining(new \DateTimeImmutable('2026-09-15T00:00:00Z'));
	}
}
