<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\UsageStatistics\Collector;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\UsageStatistics\Contract\IMetricCollector;
use OCA\Libresign\Service\UsageStatistics\Model\MetricValue;
use OCA\Libresign\Service\UsageStatistics\Model\ReportMode;
use OCA\Libresign\Service\UsageStatistics\Model\ReportPeriod;
use OCP\App\IAppManager;
use OCP\IAppConfig;

/**
 * Current LibreSign configuration. Values are read from the stored settings
 * only: building the certificate engine could create the PKI directory or
 * contact the CFSSL server, and a report must not change anything.
 */
class LibreSignConfigurationCollector implements IMetricCollector {
	private const CERTIFICATE_ENGINES = ['openssl', 'cfssl', 'none'];

	public function __construct(
		private IAppManager $appManager,
		private IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function metricIds(): array {
		return [
			'libresign.version',
			'libresign.signature_engine',
			'libresign.signing_mode',
			'certificate.engine',
			'certificate.root_ca_configured',
		];
	}

	#[\Override]
	public function collect(ReportPeriod $period, ReportMode $mode): array {
		if ($mode === ReportMode::HISTORICAL) {
			return array_fill_keys($this->metricIds(), MetricValue::unavailable());
		}

		$certificateEngine = $this->certificateEngine();
		return [
			'libresign.version' => MetricValue::of($this->appManager->getAppVersion(Application::APP_ID)),
			'libresign.signature_engine' => MetricValue::of($this->signatureEngine()),
			'libresign.signing_mode' => MetricValue::of($this->signingMode()),
			'certificate.engine' => MetricValue::of($certificateEngine),
			'certificate.root_ca_configured' => MetricValue::of($this->isRootCaConfigured($certificateEngine)),
		];
	}

	/** Same fallback as the admin settings: anything but PhpNative runs JSignPdf. */
	private function signatureEngine(): string {
		$engine = $this->appConfig->getValueString(Application::APP_ID, 'signature_engine', 'JSignPdf');
		return $engine === 'PhpNative' ? $engine : 'JSignPdf';
	}

	/** Same fallback as the signing mode policy. */
	private function signingMode(): string {
		$mode = $this->appConfig->getValueString(Application::APP_ID, 'signing_mode', 'sync');
		return in_array($mode, ['sync', 'async'], true) ? $mode : 'sync';
	}

	private function certificateEngine(): string {
		$engine = $this->appConfig->getValueString(Application::APP_ID, 'certificate_engine', 'openssl');
		return in_array($engine, self::CERTIFICATE_ENGINES, true) ? $engine : 'other';
	}

	private function isRootCaConfigured(string $certificateEngine): bool {
		if (!in_array($certificateEngine, ['openssl', 'cfssl'], true)) {
			return false;
		}
		$configPath = $this->appConfig->getValueString(Application::APP_ID, 'config_path');
		if ($configPath === '' || !is_dir($configPath)) {
			return false;
		}
		return file_exists($configPath . DIRECTORY_SEPARATOR . 'ca.pem')
			&& file_exists($configPath . DIRECTORY_SEPARATOR . 'ca-key.pem');
	}
}
