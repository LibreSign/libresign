<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\SetupCheck;

use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

class CertificateEngineSetupCheck implements ISetupCheck {
	private IL10N $l10n;
	private CertificateEngineFactory $certificateEngineFactory;

	public function __construct(
		IL10N $l10n,
		CertificateEngineFactory $certificateEngineFactory,
	) {
		$this->l10n = $l10n;
		$this->certificateEngineFactory = $certificateEngineFactory;
	}

	#[\Override]
	public function getName(): string {
		return $this->l10n->t('Certificate engine');
	}

	#[\Override]
	public function getCategory(): string {
		return 'security';
	}

	#[\Override]
	public function run(): SetupResult {
		try {
			$engine = $this->certificateEngineFactory->getEngine();
			$checkResults = $engine->configureCheck();
		} catch (\Throwable $e) {
			$engineName = $this->certificateEngineFactory->getEngine()->getName() ?? 'unknown';
			return SetupResult::error(
				$this->l10n->t('Define the certificate engine to use'),
				$this->l10n->t('Run occ libresign:configure:%s --help', [$engineName])
			);
		}

		// Process results: if any error, return error; else if any warning, return warning; else success
		$hasError = false;
		$hasWarning = false;
		$messages = [];
		$tips = [];

		foreach ($checkResults as $result) {
			if ($result instanceof ConfigureCheckHelper) {
				$status = $result->getStatus();
				$msg = $result->getMessage();
				$tip = $result->getTip();

				if ($status === 'error') {
					$hasError = true;
					$messages[] = "[ERROR] $msg";
				} elseif ($status === 'warning') {
					$hasWarning = true;
					$messages[] = "[WARNING] $msg";
				} else {
					$messages[] = $msg;
				}

				if (!empty($tip)) {
					$tips[] = $tip;
				}
			}
		}

		$tip = '';
		if (!empty($tips)) {
			$tip = implode("\n", array_unique($tips));
		}

		if ($hasError) {
			return SetupResult::error(implode("\n", $messages), $tip);
		} elseif ($hasWarning) {
			return SetupResult::warning(implode("\n", $messages), $tip);
		} else {
			return SetupResult::success(implode("\n", $messages) ?: $this->l10n->t('Certificate engine is configured correctly'));
		}
	}
}
