<?php

declare(strict_types=1);

namespace OCA\Libresign\Service;

use OCP\SetupCheck\ISetupCheckManager;

class SetupCheckResultService {

	public function __construct(
		private ISetupCheckManager $checkManager,
	) {
	}

	/**
	 * Get formatted results of all LibreSign setup checks.
	 *
	 * @return list<array{
	 *     status: 'error'|'info'|'success',
	 *     resource: string,
	 *     message: string,
	 *     tip: string,
	 *     category: string
	 * }>
	 */
	public function getFormattedChecks(): array {
		$allResults = $this->checkManager->runAll();
		$formatted = [];

		foreach ($allResults as $category => $checks) {
			foreach ($checks as $checkName => $result) {
				if (!str_starts_with($checkName, 'OCA\\Libresign\\SetupCheck\\')) {
					continue;
				}
				$formatted[] = [
					'status' => $this->mapSeverityToStatus($result->getSeverity()),
					'resource' => $this->formatResourceName($checkName),
					'message' => (string)$result->getDescription(), // garantia de string
					'tip' => $result->getLinkToDoc() ?? '',
					'category' => $category,
				];
			}
		}

		return $formatted;
	}

	/**
	 * Get formatted checks without category, suitable for legacy API.
	 *
	 * @return list<array{
	 *     status: 'error'|'info'|'success',
	 *     resource: string,
	 *     message: string,
	 *     tip: string
	 * }>
	 */
	public function getLegacyFormattedChecks(): array {
		$checks = $this->getFormattedChecks();
		return array_map(function ($check) {
			unset($check['category']);
			return $check;
		}, $checks);
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
