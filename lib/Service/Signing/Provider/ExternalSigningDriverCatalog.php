<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\Provider;

use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\ExperimentalFeaturesService;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscDriver;

/**
 * Read-only definitions of supported driver types, never configured instances.
 *
 * @psalm-import-type LibresignExternalSigningDriver from ResponseDefinitions
 */
final class ExternalSigningDriverCatalog {
	public function __construct(
		private ExperimentalFeaturesService $experimentalFeaturesService,
		private CscDriver $cscDriver,
	) {
	}

	/** @return list<LibresignExternalSigningDriver> */
	public function listDrivers(): array {
		if (!$this->experimentalFeaturesService->isEnabled()) {
			return [];
		}

		return [$this->cscDriver->metadata()];
	}
}
