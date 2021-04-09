<?php

namespace OCA\Libresign\Settings;

use OCA\Libresign\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class Admin implements ISettings {
	public function getForm() {
		return new TemplateResponse(Application::APP_ID, 'admin_settings');
	}

	public function getSection() {
		return Application::APP_ID;
	}

	public function getPriority() {
		return 100;
	}
}
