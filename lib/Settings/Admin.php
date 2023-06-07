<?php

namespace OCA\Libresign\Settings;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\Handler as CertificateEngine;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\Util;

class Admin implements ISettings {
	public function __construct(
		private IInitialState $initialState,
		private IdentifyMethodService $identifyMethodService,
		private CertificateEngine $certificateEngine,
		private IConfig $config
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
			$this->certificateEngine->getEngine()->getName()
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
