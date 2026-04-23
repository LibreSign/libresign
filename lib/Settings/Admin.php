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
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\CertificatePolicyService;
use OCA\Libresign\Service\DocMdp\ConfigService as DocMdpConfigService;
use OCA\Libresign\Service\FooterService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IUserSession;
use OCP\Settings\ISettings;
use OCP\Util;

/**
 * @psalm-import-type LibresignAdminSignatureEngine from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignAdminSigningMode from \OCA\Libresign\ResponseDefinitions
 * @psalm-import-type LibresignAdminWorkerType from \OCA\Libresign\ResponseDefinitions
 */
class Admin implements ISettings {
	public const PASSWORD_PLACEHOLDER = '••••••••';

	public function __construct(
		private IInitialState $initialState,
		private AccountService $accountService,
		private IUserSession $userSession,
		private IdentifyMethodService $identifyMethodService,
		private CertificateEngineFactory $certificateEngineFactory,
		private CertificatePolicyService $certificatePolicyService,
		private IAppConfig $appConfig,
		private SignatureTextService $signatureTextService,
		private SignatureBackgroundService $signatureBackgroundService,
		private FooterService $footerService,
		private DocMdpConfigService $docMdpConfigService,
		private PolicyService $policyService,
	) {
	}
	#[\Override]
	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'libresign-settings');
		Util::addStyle(Application::APP_ID, 'libresign-settings');
		$this->initialState->provideInitialState('config', $this->accountService->getConfig($this->userSession->getUser()));
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
		$this->initialState->provideInitialState('legal_information', $this->appConfig->getValueString(Application::APP_ID, 'legal_information', ''));
		$this->initialState->provideInitialState('signature_available_variables', $this->signatureTextService->getAvailableVariables());
		$this->initialState->provideInitialState('signature_background_type', $this->signatureBackgroundService->getSignatureBackgroundType());
		$this->initialState->provideInitialState('signature_font_size', $this->signatureTextService->getSignatureFontSize());
		$this->initialState->provideInitialState('signature_height', $this->signatureTextService->getFullSignatureHeight());
		$this->initialState->provideInitialState('signature_preview_zoom_level', $this->appConfig->getValueFloat(Application::APP_ID, 'signature_preview_zoom_level', 100));
		$this->initialState->provideInitialState('footer_preview_zoom_level', $this->appConfig->getValueFloat(Application::APP_ID, 'footer_preview_zoom_level', 100));
		$this->initialState->provideInitialState('footer_preview_width', $this->appConfig->getValueInt(Application::APP_ID, 'footer_preview_width', 595));
		$this->initialState->provideInitialState('footer_preview_height', $this->appConfig->getValueInt(Application::APP_ID, 'footer_preview_height', 100));
		$this->initialState->provideInitialState('footer_template_variables', $this->footerService->getTemplateVariablesMetadata());
		$this->initialState->provideInitialState('footer_template', $this->footerService->getTemplate());
		$this->initialState->provideInitialState('footer_template_is_default', $this->footerService->isDefaultTemplate());
		$this->initialState->provideInitialState('signature_engine', $this->getSignatureEngineInitialState());
		$this->initialState->provideInitialState('signature_render_mode', $this->signatureTextService->getRenderMode());
		$this->initialState->provideInitialState('signature_text_template', $this->signatureTextService->getTemplate());
		$this->initialState->provideInitialState('signature_width', $this->signatureTextService->getFullSignatureWidth());
		$this->initialState->provideInitialState('template_font_size', $this->signatureTextService->getTemplateFontSize());
		$this->initialState->provideInitialState('tsa_url', $this->appConfig->getValueString(Application::APP_ID, 'tsa_url', ''));
		$this->initialState->provideInitialState('tsa_policy_oid', $this->appConfig->getValueString(Application::APP_ID, 'tsa_policy_oid', ''));
		$this->initialState->provideInitialState('tsa_auth_type', $this->appConfig->getValueString(Application::APP_ID, 'tsa_auth_type', 'none'));
		$this->initialState->provideInitialState('tsa_username', $this->appConfig->getValueString(Application::APP_ID, 'tsa_username', ''));
		$this->initialState->provideInitialState('tsa_password', $this->appConfig->getValueString(Application::APP_ID, 'tsa_password', self::PASSWORD_PLACEHOLDER));
		$this->initialState->provideInitialState('docmdp_config', $this->docMdpConfigService->getConfig());
		$resolvedPolicies = [];
		foreach ($this->policyService->resolveKnownPolicies() as $policyKey => $resolvedPolicy) {
			$resolvedPolicies[$policyKey] = $resolvedPolicy->toArray();
		}
		$this->initialState->provideInitialState('effective_policies', [
			'policies' => $resolvedPolicies,
		]);
		$this->initialState->provideInitialState('signing_mode', $this->getSigningModeInitialState());
		$this->initialState->provideInitialState('worker_type', $this->getWorkerTypeInitialState());
		$this->initialState->provideInitialState('identification_documents', $this->appConfig->getValueBool(Application::APP_ID, 'identification_documents', false));
		$this->initialState->provideInitialState('approval_group', $this->appConfig->getValueArray(Application::APP_ID, 'approval_group', ['admin']));
		$this->initialState->provideInitialState('envelope_enabled', $this->appConfig->getValueBool(Application::APP_ID, 'envelope_enabled', true));
		$this->initialState->provideInitialState('parallel_workers', $this->appConfig->getValueString(Application::APP_ID, 'parallel_workers', '4'));
		$this->initialState->provideInitialState('show_confetti_after_signing', $this->appConfig->getValueBool(Application::APP_ID, 'show_confetti_after_signing', true));
		$this->initialState->provideInitialState('crl_external_validation_enabled', $this->appConfig->getValueBool(Application::APP_ID, 'crl_external_validation_enabled', true));
		$this->initialState->provideInitialState('ldap_extension_available', function_exists('ldap_connect'));

		$response = new TemplateResponse(Application::APP_ID, 'admin_settings');
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedWorkerSrcDomain("'self'");
		$policy->addAllowedWorkerSrcDomain('blob:');
		$response->setContentSecurityPolicy($policy);

		return $response;
	}

	/**
	 * @psalm-return 'libresign'
	 */
	#[\Override]
	public function getSection(): string {
		return Application::APP_ID;
	}

	/**
	 * @psalm-return 100
	 */
	#[\Override]
	public function getPriority(): int {
		return 100;
	}

	/** @return LibresignAdminSignatureEngine */
	private function getSignatureEngineInitialState(): string {
		$engine = $this->appConfig->getValueString(Application::APP_ID, 'signature_engine', 'JSignPdf');
		if ($engine === 'PhpNative') {
			return $engine;
		}
		return 'JSignPdf';
	}

	/** @return LibresignAdminSigningMode */
	private function getSigningModeInitialState(): string {
		$mode = $this->appConfig->getValueString(Application::APP_ID, 'signing_mode', 'sync');
		if ($mode === 'async') {
			return $mode;
		}
		return 'sync';
	}

	/** @return LibresignAdminWorkerType */
	private function getWorkerTypeInitialState(): string {
		$workerType = $this->appConfig->getValueString(Application::APP_ID, 'worker_type', 'local');
		if ($workerType === 'external') {
			return $workerType;
		}
		return 'local';
	}
}
