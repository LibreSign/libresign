<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Settings;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\CertificatePolicyService;
use OCA\Libresign\Service\Policy\PolicyService;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IUserSession;
use OCP\Settings\ISettings;
use OCP\Util;

class Admin implements ISettings {
	public const PASSWORD_PLACEHOLDER = '••••••••';

	public function __construct(
		private IInitialState $initialState,
		private AccountService $accountService,
		private IUserSession $userSession,
		private CertificateEngineFactory $certificateEngineFactory,
		private CertificatePolicyService $certificatePolicyService,
		private IAppConfig $appConfig,
		private PolicyService $policyService,
	) {
	}
	#[\Override]
	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'libresign-settings');
		Util::addStyle(Application::APP_ID, 'libresign-settings');
		$this->initialState->provideInitialState('config', $this->accountService->getConfig($this->userSession->getUser()));
		$this->initialState->provideInitialState('certificate_engine', $this->certificateEngineFactory->getEngine()->getName());
		$this->initialState->provideInitialState('certificate_policies_oid', $this->certificatePolicyService->getOid());
		$this->initialState->provideInitialState('certificate_policies_cps', $this->certificatePolicyService->getCps());
		$this->initialState->provideInitialState('config_path', $this->appConfig->getValueString(Application::APP_ID, 'config_path'));
		$this->initialState->provideInitialState('signature_engine', $this->getSignatureEngineInitialState());
		$resolvedPolicies = [];
		foreach ($this->policyService->resolveKnownPolicies() as $policyKey => $resolvedPolicy) {
			$resolvedPolicies[$policyKey] = $resolvedPolicy->toArray();
		}
		$this->initialState->provideInitialState('effective_policies', [
			'policies' => $resolvedPolicies,
		]);
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

	private function getSignatureEngineInitialState(): string {
		$engine = $this->appConfig->getValueString(Application::APP_ID, 'signature_engine', 'JSignPdf');
		if ($engine === 'PhpNative') {
			return $engine;
		}
		return 'JSignPdf';
	}

}
