<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCA\Libresign\Service\SetupCheck\ConfigureCheckResult;
use OCP\SetupCheck\ISetupCheckManager;

class SetupCheckResultService {

	/** @var array<string, string> */
	private const RESOURCE_MAP = [
		'OCA\\Libresign\\SetupCheck\\JavaSetupCheck' => 'java',
		'OCA\\Libresign\\SetupCheck\\JSignPdfSetupCheck' => 'jsignpdf',
		'OCA\\Libresign\\SetupCheck\\PDFtkSetupCheck' => 'pdftk',
		'OCA\\Libresign\\SetupCheck\\PopplerSetupCheck' => 'poppler',
		'OCA\\Libresign\\SetupCheck\\ImagickSetupCheck' => 'imagick',
	];

	public function __construct(
		private ISetupCheckManager $checkManager,
		private CertificateEngineFactory $certificateEngineFactory,
	) {
	}

	/**
	 * @return list<ConfigureCheckResult>
	 */
	public function getFormattedChecks(): array {
		return array_merge(
			$this->getBinaryChecks(),
			$this->getCertificateEngineChecks(),
		);
	}

	/**
	 * @return list<ConfigureCheckResult>
	 */
	private function getBinaryChecks(): array {
		$allResults = $this->checkManager->runAll();
		$formatted = [];

		foreach ($allResults as $category => $checks) {
			foreach ($checks as $checkName => $result) {
				if (!isset(self::RESOURCE_MAP[$checkName])) {
					continue;
				}
				$formatted[] = new ConfigureCheckResult(
					$this->mapSeverityToStatus($result->getSeverity()),
					self::RESOURCE_MAP[$checkName],
					(string)$result->getDescription(),
					$result->getLinkToDoc() ?? '',
					$category,
				);
			}
		}

		return $formatted;
	}

	/**
	 * @return list<ConfigureCheckResult>
	 */
	private function getCertificateEngineChecks(): array {
		$engineChecks = $this->certificateEngineFactory->getEngine()->configureCheck();

		$formatted = [];
		foreach ($engineChecks as $check) {
			if (!$check instanceof ConfigureCheckHelper) {
				continue;
			}
			$formatted[] = new ConfigureCheckResult(
				$check->getStatus(),
				$check->getResource(),
				$check->getMessage(),
				$check->getTip(),
				'security',
			);
		}

		return $formatted;
	}

	/**
	 * Map SetupResult severity to legacy status string.
	 *
	 * @param string $severity (error, warning, success)
	 * @return 'error'|'info'|'success'
	 */
	private function mapSeverityToStatus(string $severity): string {
		return match ($severity) {
			'error' => 'error',
			'warning' => 'info',
			'success' => 'success',
			default => 'info',
		};
	}
}
