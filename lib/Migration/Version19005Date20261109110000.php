<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Remove obsolete direct callback destinations when upgrading to native
 * Nextcloud webhook subscriptions. Existing signing files are unaffected.
 */
final class Version19005Date20261109110000 extends SimpleMigrationStep {
	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	/** @param Closure(): ISchemaWrapper $schemaClosure */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('libresign_file')) {
			return null;
		}

		$table = $schema->getTable('libresign_file');
		if (!$table->hasColumn('callback')) {
			return null;
		}

		$table->dropColumn('callback');
		return $schema;
	}

	/** @param Closure(): ISchemaWrapper $schemaClosure */
	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if (in_array('webhook_sign_url', $this->appConfig->getKeys(Application::APP_ID), true)) {
			$this->appConfig->deleteKey(Application::APP_ID, 'webhook_sign_url');
		}
	}
}
