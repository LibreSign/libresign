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
use OCA\Libresign\Service\CertificatePolicyService;
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
		private CertificatePolicyService $certificatePolicyService,
		private IAppConfig $appConfig,
		private SignatureTextService $signatureTextService,
		private SignatureBackgroundService $signatureBackgroundService,
	) {
	}
	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'libresign-settings');
		try {
			$signatureParsed = $this->signatureTextService->parse();
			$this->initialState->provideInitialState('signature_text_parsed', $signatureParsed['parsed']);
		} catch (LibresignException $e) {
			$this->initialState->provideInitialState('signature_text_parsed', '');
			$this->initialState->provideInitialState('signature_text_template_error', $e->getMessage());
		}
		$this->initialState->provideInitialState('certificate_engine', $this->certificateEngineFactory->getEngine()->getName());
		$this->initialState->provideInitialState('certificate_policies_oid', $this->certificatePolicyService->getOid());
		$this->initialState->provideInitialState('certificate_policies_cps', $this->certificatePolicyService->getCps());
		$this->initialState->provideInitialState('config_path', $this->appConfig->getValueString(Application::APP_ID, 'config_path'));
		$this->initialState->provideInitialState('default_signature_font_size', SignatureTextService::SIGNATURE_DEFAULT_FONT_SIZE);
		$this->initialState->provideInitialState('default_signature_height', SignatureTextService::DEFAULT_SIGNATURE_HEIGHT);
		$this->initialState->provideInitialState('default_signature_text_template', $this->signatureTextService->getDefaultTemplate());
		$this->initialState->provideInitialState('default_signature_width', SignatureTextService::DEFAULT_SIGNATURE_WIDTH);
		$this->initialState->provideInitialState('default_template_font_size', $this->signatureTextService->getDefaultTemplateFontSize());
		$this->initialState->provideInitialState('identify_methods', $this->identifyMethodService->getIdentifyMethodsSettings());
		$this->initialState->provideInitialState('signature_available_variables', $this->signatureTextService->getAvailableVariables());
		$this->initialState->provideInitialState('signature_background_type', $this->signatureBackgroundService->getSignatureBackgroundType());
		$this->initialState->provideInitialState('signature_font_size', $this->signatureTextService->getSignatureFontSize());
		$this->initialState->provideInitialState('signature_height', $this->signatureTextService->getFullSignatureHeight());
		$this->initialState->provideInitialState('signature_preview_zoom_level', $this->appConfig->getValueFloat(Application::APP_ID, 'signature_preview_zoom_level', 100));
		$this->initialState->provideInitialState('signature_render_mode', $this->signatureTextService->getRenderMode());
		$this->initialState->provideInitialState('signature_text_template', $this->signatureTextService->getTemplate());
		$this->initialState->provideInitialState('signature_width', $this->signatureTextService->getFullSignatureWidth());
		$this->initialState->provideInitialState('template_font_size', $this->signatureTextService->getTemplateFontSize());
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
