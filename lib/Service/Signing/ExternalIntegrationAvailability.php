<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Service\ExperimentalFeaturesService;

/**
 * Distinguish the global rollout gate, the instance's administrative switch,
 * and the driver's independently reported technical availability.
 */
final class ExternalIntegrationAvailability {
	public function __construct(
		private ExperimentalFeaturesService $experimentalFeatures,
	) {
	}

	public function canStartSigning(ExternalIntegrationRecord $integration, bool $driverAvailable): bool {
		return $this->experimentalFeatures->isEnabled()
			&& $integration->enabled
			&& $driverAvailable;
	}
}
