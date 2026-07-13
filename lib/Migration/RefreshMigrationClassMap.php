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
