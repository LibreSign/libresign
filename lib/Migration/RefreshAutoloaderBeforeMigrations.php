<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Refresh the LibreSign Composer autoloader immediately before Nextcloud executes migrations.
 *
 * Why this exists:
 * - Nextcloud can load enabled apps earlier in the same PHP process, especially during OCC bootstrap.
 *   We verified this through the CLI path `console.php` -> `OC::init()` -> `AppManager::loadApps()`.
 * - When an update later drops new LibreSign classes into place, the original Composer loader can
 *   keep previous class misses cached. In the bug that triggered this step, `MigrationService`
 *   reported `Migration step ... is unknown` for a newly introduced migration class.
 * - Requiring `composer/autoload.php` again is not enough in this flow: `OC_App::registerAutoloading()`
 *   uses `require_once`, and Composer keeps the app-specific loader in static state after the first
 *   bootstrap. We therefore rebuild a fresh loader from Composer's generated metadata files.
 * - This is the safest app-level mitigation we found, not a full hot-reload primitive: PHP still
 *   cannot redefine classes or functions that were already loaded earlier in the same request. A
 *   complete fix for that broader limitation would need to happen in Nextcloud core by upgrading the
 *   app before it is booted, or by performing the upgrade in a fresh PHP process.
 * - Keeping this logic in a `pre-migration` repair step is still the earliest reliable app-level
 *   extension point in `Installer::updateAppstoreApp()` -> `AppManager::upgradeApp()`.
 *
 * References:
 * - LibreSign/libresign#7892
 * - Related but different root cause: nextcloud/calendar_resource_management#238 / #239 fixed an
 *   OCC upgrade by removing `classmap-authoritative`; LibreSign's main app autoloader does not
 *   enable that flag.
 *
 * If this step is ever removed or changed, reproduce those upgrade scenarios first and confirm that
 * newly introduced LibreSign classes still load correctly in both web- and OCC-driven upgrades.
 */
final class RefreshAutoloaderBeforeMigrations implements IRepairStep {
	#[\Override]
	public function getName(): string {
		return 'Refresh LibreSign autoloader before migrations';
	}

	#[\Override]
	public function run(IOutput $output): void {
		UpgradeSafeAutoloader::registerCurrentAppLoader(dirname(__DIR__, 2));
	}
}
