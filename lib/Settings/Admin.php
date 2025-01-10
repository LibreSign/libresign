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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Settings;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngineHandler;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\Settings\ISettings;
use OCP\Util;

class Admin implements ISettings {
	public function __construct(
		private IInitialState $initialState,
		private IdentifyMethodService $identifyMethodService,
		private CertificateEngineHandler $certificateEngineHandler,
		private IAppConfig $appConfig,
	) {
	}
	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'libresign-settings');
		$this->initialState->provideInitialState(
			'identify_methods',
			$this->identifyMethodService->getIdentifyMethodsSettings()
		);
		$this->initialState->provideInitialState(
			'certificate_engine',
			$this->certificateEngineHandler->getEngine()->getName()
		);
		$this->initialState->provideInitialState(
			'config_path',
			$this->appConfig->getValueString(Application::APP_ID, 'config_path')
		);
		return new TemplateResponse(Application::APP_ID, 'admin_settings');
	}

	/**
	 * @psalm-return 'libresign'
	 */
	public function getSection(): string {
		return Application::APP_ID;
	}

	/**
	 * @psalm-return 100
	 */
	public function getPriority(): int {
		return 100;
	}
}
