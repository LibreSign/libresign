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
use OCA\Libresign\SetupCheck\ImagickSetupCheck;
use OCA\Libresign\SetupCheck\JavaSetupCheck;
use OCA\Libresign\SetupCheck\JSignPdfSetupCheck;
use OCA\Libresign\SetupCheck\PDFtkSetupCheck;
use OCA\Libresign\SetupCheck\PopplerSetupCheck;
use OCP\SetupCheck\ISetupCheck;

class SetupCheckResultService {

	public function __construct(
		private CertificateEngineFactory $certificateEngineFactory,
		private JavaSetupCheck $javaSetupCheck,
		private JSignPdfSetupCheck $jSignPdfSetupCheck,
		private PDFtkSetupCheck $pdftkSetupCheck,
		private PopplerSetupCheck $popplerSetupCheck,
		private ImagickSetupCheck $imagickSetupCheck,
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
		/** @var array<string, ISetupCheck> $checks */
		$checks = [
			'java' => $this->javaSetupCheck,
			'jsignpdf' => $this->jSignPdfSetupCheck,
			'pdftk' => $this->pdftkSetupCheck,
			'poppler' => $this->popplerSetupCheck,
			'imagick' => $this->imagickSetupCheck,
		];

		$formatted = [];
		foreach ($checks as $resource => $check) {
			$result = $check->run();
			$formatted[] = new ConfigureCheckResult(
				$this->mapSeverityToStatus($result->getSeverity()),
				$resource,
				(string)$result->getDescription(),
				$result->getLinkToDoc() ?? '',
				$check->getCategory(),
			);
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
