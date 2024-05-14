<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\Service\Install\InstallService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version8000Date20230730032402 extends SimpleMigrationStep {
	public function __construct(
		protected InstallService $installService,
		protected IAppConfig $appConfig,
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->installService->installPdftk();
		if ($rootCert = $this->appConfig->getAppValue('rootCert')) {
			$this->appConfig->deleteAppValue('rootCert');
			$this->appConfig->setAppValue('root_cert', $rootCert);
		}
		if ($notifyUnsignedUser = $this->appConfig->getAppValue('notifyUnsignedUser', '')) {
			$this->appConfig->setAppValue('notify_unsigned_user', $notifyUnsignedUser);
		}
		$this->appConfig->deleteAppValue('notifyUnsignedUser');
	}
}
