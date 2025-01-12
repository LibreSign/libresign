<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
