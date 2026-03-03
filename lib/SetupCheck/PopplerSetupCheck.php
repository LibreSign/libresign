<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\SetupCheck;

use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

class PopplerSetupCheck implements ISetupCheck {
	private IL10N $l10n;

	public function __construct(IL10N $l10n) {
		$this->l10n = $l10n;
	}

	public function getName(): string {
		return $this->l10n->t('Poppler utils');
	}

	public function getCategory(): string {
		return 'system';
	}

	public function run(): SetupResult {
		$pdfsigOk = $this->checkPdfSig();
		$pdfinfoOk = $this->checkPdfinfo();

		if ($pdfsigOk && $pdfinfoOk) {
			return SetupResult::success(
				$this->l10n->t('pdfsig version: %s, pdfinfo version: %s', [$pdfsigOk, $pdfinfoOk])
			);
		}

		$messages = [];
		$tip = $this->l10n->t('Install the package poppler-utils on your operating system to enable PDF signature validation and page dimension detection.');

		if (!$pdfsigOk) {
			$messages[] = $this->l10n->t('pdfsig not installed or not working');
		}
		if (!$pdfinfoOk) {
			$messages[] = $this->l10n->t('pdfinfo not installed or not working');
		}

		// Since Poppler is optional, return info instead of error
		return SetupResult::info(implode('; ', $messages), $tip);
	}

	private function checkPdfSig(): ?string {
		exec('pdfsig -v 2>&1', $output, $retval);
		if ($retval !== 0 || empty($output)) {
			return null;
		}
		$full = implode(PHP_EOL, $output);
		if (preg_match('/pdfsig version (?<version>.*)/', $full, $matches)) {
			return $matches['version'];
		}
		return null;
	}

	private function checkPdfinfo(): ?string {
		exec('pdfinfo -v 2>&1', $output, $retval);
		if ($retval !== 0 || empty($output)) {
			return null;
		}
		$full = implode(PHP_EOL, $output);
		if (preg_match('/pdfinfo version (?<version>.*)/', $full, $matches)) {
			return $matches['version'];
		}
		return null;
	}
}
