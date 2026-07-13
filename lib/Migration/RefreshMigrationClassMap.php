<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use OCA\Libresign\Bootstrap\UpgradeSafeAutoloader;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Refresh the Composer class map for migrations immediately before Nextcloud executes them.
 *
 * Why this exists:
 * - Nextcloud can load enabled apps earlier in the same PHP process, especially during OCC bootstrap.
 *   We verified this through the CLI path `console.php` -> `OC::init()` -> `AppManager::loadApps()`.
 * - When an update later drops a new `Version*.php` migration into place, Composer can keep a
 *   previous class miss cached and `MigrationService` reports `Migration step ... is unknown`.
 * - Keeping this logic in a `pre-migration` repair step is more reliable than relying on
 *   `composer/autoload.php`, because `OC_App::registerAutoloading()` uses `require_once` and that
 *   entrypoint may already have been included before `Installer::updateAppstoreApp()` hands control
 *   to `AppManager::upgradeApp()` for the real migration execution.
 *
 * References:
 * - LibreSign/libresign#7892
 * - nextcloud/calendar_resource_management#238
 *
 * If this step is ever removed or changed, reproduce those upgrade scenarios first and confirm that
 * new LibreSign migrations still load correctly in both web- and OCC-driven upgrades.
 */
final class RefreshMigrationClassMap implements IRepairStep {
	#[\Override]
	public function getName(): string {
		return 'Refresh LibreSign migration class map';
	}

	#[\Override]
	public function run(IOutput $output): void {
		UpgradeSafeAutoloader::registerCurrentAppLoader(dirname(__DIR__, 2));
	}
}
