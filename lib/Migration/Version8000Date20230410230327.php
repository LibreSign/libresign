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

use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
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

	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$libresignCliPath = $this->appConfig->getAppValue('libresign_cli_path');
		if (!$libresignCliPath) {
			return;
		}
		$appFolder = $this->appData->getFolder('/');
		try {
			$folder = $appFolder->getFolder('libresign-cli');
			$folder->delete();
		} catch (NotFoundException $e) {
		}
		$this->appConfig->deleteAppValue('libresign_cli_path');
	}
}
