<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use OCA\Libresign\AppInfo\Application;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version8000Date20230410230327 extends SimpleMigrationStep {
	protected IAppData $appData;

	public function __construct(
		protected IAppConfig $appConfig,
		protected IAppDataFactory $appDataFactory,
	) {
		$this->appData = $appDataFactory->get('libresign');
	}

	#[\Override]
	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$libresignCliPath = $this->appConfig->getValueString(Application::APP_ID, 'libresign_cli_path');
		if (!$libresignCliPath) {
			return;
		}
		$appFolder = $this->appData->getFolder('/');
		try {
			$folder = $appFolder->getFolder('libresign-cli');
			$folder->delete();
		} catch (NotFoundException) {
		}
		$this->appConfig->deleteKey(Application::APP_ID, 'libresign_cli_path');
	}
}
