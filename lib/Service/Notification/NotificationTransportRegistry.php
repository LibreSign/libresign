<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

/** Resolve the same transport in HTTP and background-job processes. */
final class NotificationTransportRegistry {
	public function __construct(
		private NotificationModuleCatalog $modules,
	) {
	}

	public function resolve(string $channel): NotificationChannel {
		$transport = null;
		foreach ($this->modules->all() as $module) {
			$candidate = $module->transport();
			if (!$candidate->supports($channel)) {
				continue;
			}
			if ($transport !== null) {
				throw new \LogicException('Multiple notification modules support the same channel');
			}
			$transport = $candidate;
		}
		return $transport ?? throw new \LogicException('No notification module supports the queued channel');
	}
}
