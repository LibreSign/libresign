<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\SignatureTextService;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version17003Date20260404000000 extends SimpleMigrationStep {
	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->sanitizeDimension(
			$output,
			'signature_width',
			SignatureTextService::DEFAULT_SIGNATURE_WIDTH,
		);
		$this->sanitizeDimension(
			$output,
			'signature_height',
			SignatureTextService::DEFAULT_SIGNATURE_HEIGHT,
		);
	}

	private function sanitizeDimension(IOutput $output, string $key, float $default): void {
		$stored = $this->appConfig->getValueFloat(Application::APP_ID, $key, $default);
		if (is_finite($stored) && $stored >= SignatureTextService::SIGNATURE_DIMENSION_MINIMUM) {
			return;
		}

		$this->appConfig->setValueFloat(Application::APP_ID, $key, $default);
		$output->warning(sprintf(
			'Restored invalid "%s" value "%s" to default "%s".',
			$key,
			(string)$stored,
			(string)$default,
		));
	}
}
