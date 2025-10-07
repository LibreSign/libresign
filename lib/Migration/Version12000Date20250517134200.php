<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version12000Date20250517134200 extends SimpleMigrationStep {
	public function __construct(
		protected IConfig $config,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[\Override]
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$keys = $this->config->getAppKeys(Application::APP_ID);
		if (in_array('notify_unsigned_user', $keys)) {
			$current = $this->config->getAppValue(Application::APP_ID, 'notify_unsigned_user');
			$this->config->setAppValue('activity', 'notify_email_libresign_file_to_sign', $current ? '1' : '0');
			$this->config->deleteAppValue(Application::APP_ID, 'notify_unsigned_user');
		}
	}
}
