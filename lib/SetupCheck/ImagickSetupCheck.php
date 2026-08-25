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

class ImagickSetupCheck implements ISetupCheck {
	private IL10N $l10n;

	public function __construct(IL10N $l10n) {
		$this->l10n = $l10n;
	}

	#[\Override]
	public function getName(): string {
		// TRANSLATORS Name of the Nextcloud administration overview check for the Imagick library used to render visible signatures.
		return $this->l10n->t('Imagick PHP extension');
	}

	#[\Override]
	public function getCategory(): string {
		return 'system';
	}

	#[\Override]
	public function run(): SetupResult {
		if (!extension_loaded('imagick')) {
			return SetupResult::info(
				// TRANSLATORS Warning shown in Nextcloud administration overview when the Imagick library is not available.
				$this->l10n->t('Imagick extension is not loaded'),
				// TRANSLATORS Tip under a Nextcloud administration overview warning. Explains installing Imagick to enable visible signatures and related image rendering.
				$this->l10n->t('Install php-imagick to enable visible signatures, background images, and signature element rendering.')
			);
		}
		// TRANSLATORS Success message in Nextcloud administration overview when the Imagick library is available.
		return SetupResult::success($this->l10n->t('Imagick extension is loaded'));
	}
}
