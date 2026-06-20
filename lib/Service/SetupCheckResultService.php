<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Service\SetupCheck\ConfigureCheckResult;
use OCP\SetupCheck\ISetupCheckManager;

class SetupCheckResultService {

	public function __construct(
		private ISetupCheckManager $checkManager,
	) {
	}

	/**
	 * @return list<ConfigureCheckResult>
	 */
	public function getFormattedChecks(): array {
		$allResults = $this->checkManager->runAll();
		$formatted = [];

		foreach ($allResults as $category => $checks) {
			foreach ($checks as $checkName => $result) {
				if (!str_starts_with($checkName, 'OCA\\Libresign\\SetupCheck\\')) {
					continue;
				}
				$formatted[] = new ConfigureCheckResult(
					$this->mapSeverityToStatus($result->getSeverity()),
					$this->formatResourceName($checkName),
					(string)$result->getDescription(),
					$result->getLinkToDoc() ?? '',
					$category,
				);
			}
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

	/**
	 * Format a fully qualified class name to a short resource name.
	 *
	 * @param string $checkName e.g. "OCA\Libresign\SetupCheck\JavaSetupCheck"
	 * @return string e.g. "Java"
	 */
	private function formatResourceName(string $checkName): string {
		$shortName = substr($checkName, strrpos($checkName, '\\') + 1);
		return str_replace('SetupCheck', '', $shortName);
	}
}
