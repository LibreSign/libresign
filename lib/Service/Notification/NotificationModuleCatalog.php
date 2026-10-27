<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

/** One registration point for event listeners and their transport adapters. */
final class NotificationModuleCatalog {
	/** @param list<NotificationModule> $modules */
	public function __construct(
		private array $modules,
	) {
	}

	/** @return list<NotificationModule> */
	public function all(): array {
		return $this->modules;
	}
}
