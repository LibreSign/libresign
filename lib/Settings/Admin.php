<?php

namespace OCA\Libresign\Settings;

use OCA\Libresign\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class Admin implements ISettings {
	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'admin_settings');
	}

	/**
	 * @return string
	 *
	 * @psalm-return 'libresign'
	 */
	public function getSection(): string {
		return Application::APP_ID;
	}

	/**
	 * @return int
	 *
	 * @psalm-return 100
	 */
	public function getPriority(): int {
		return 100;
	}
}
