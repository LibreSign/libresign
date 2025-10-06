<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Install\InstallService;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version8000Date20230730032402 extends SimpleMigrationStep {
	public function __construct(
		protected InstallService $installService,
		protected IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->installService->installPdftk();
		if ($rootCert = $this->appConfig->getValueArray(Application::APP_ID, 'rootCert')) {
			$this->appConfig->deleteKey(Application::APP_ID, 'rootCert');
			$this->appConfig->setValueArray(Application::APP_ID, 'root_cert', $rootCert);
		}
		if ($notifyUnsignedUser = $this->appConfig->getValueString(Application::APP_ID, 'notifyUnsignedUser', '')) {
			$this->appConfig->setValueString(Application::APP_ID, 'notify_unsigned_user', $notifyUnsignedUser);
		}
		$this->appConfig->deleteKey(Application::APP_ID, 'notifyUnsignedUser');
	}
}
