<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Settings;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\Settings\ISettings;
use OCP\Util;

class Admin implements ISettings {
	public function __construct(
		private IInitialState $initialState,
		private IdentifyMethodService $identifyMethodService,
		private CertificateEngineFactory $certificateEngineFactory,
		private IAppConfig $appConfig,
		private SignatureTextService $signatureTextService,
		private SignatureBackgroundService $signatureBackgroundService,
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
			$this->certificateEngineFactory->getEngine()->getName()
		);
		$this->initialState->provideInitialState(
			'config_path',
			$this->appConfig->getValueString(Application::APP_ID, 'config_path')
		);
		try {
			$signatureParsed = $this->signatureTextService->parse();
			$this->initialState->provideInitialState(
				'signature_text_parsed',
				$signatureParsed['parsed'],
			);
		} catch (LibresignException $e) {
			$this->initialState->provideInitialState(
				'signature_text_parsed',
				'',
			);
			$this->initialState->provideInitialState(
				'signature_text_template_error',
				$e->getMessage(),
			);
		}
		$this->initialState->provideInitialState(
			'signature_text_template',
			$this->signatureTextService->getTemplate(),
		);
		$this->initialState->provideInitialState(
			'template_font_size',
			$this->signatureTextService->getTemplateFontSize(),
		);
		$this->initialState->provideInitialState(
			'default_signature_text_template',
			$this->signatureTextService->getDefaultTemplate(),
		);
		$this->initialState->provideInitialState(
			'default_template_font_size',
			SignatureTextService::TEMPLATE_DEFAULT_FONT_SIZE,
		);
		$this->initialState->provideInitialState(
			'signature_available_variables',
			$this->signatureTextService->getAvailableVariables(),
		);
		$this->initialState->provideInitialState(
			'signature_render_mode',
			$this->signatureTextService->getRenderMode(),
		);
		$this->initialState->provideInitialState(
			'signature_background_type',
			$this->signatureBackgroundService->getSignatureBackgroundType(),
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
