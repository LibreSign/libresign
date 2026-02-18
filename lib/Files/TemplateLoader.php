<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Files;

use OCA\Files\Event\LoadSidebar;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\DocMdp\ConfigService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Util;

/**
 * @template-implements IEventListener<Event>
 */
class TemplateLoader implements IEventListener {
	public function __construct(
		private IRequest $request,
		private IUserSession $userSession,
		private AccountService $accountService,
		private IInitialState $initialState,
		private ValidateHelper $validateHelper,
		private IdentifyMethodService $identifyMethodService,
		private CertificateEngineFactory $certificateEngineFactory,
		private IAppConfig $appConfig,
		private IAppManager $appManager,
		private ConfigService $docMdpConfigService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof LoadSidebar)) {
			return;
		}

		if (!$this->appManager->isEnabledForUser('libresign')) {
			return;
		}

		foreach ($this->getInitialStatePayload() as $key => $value) {
			$this->initialState->provideInitialState($key, $value);
		}

		Util::addScript(Application::APP_ID, 'libresign-tab');
		Util::addStyle(Application::APP_ID, 'icons');
	}

	protected function getInitialStatePayload(): array {
		return [
			'certificate_ok' => $this->certificateEngineFactory->getEngine()->isSetupOk(),
			'identify_methods' => $this->identifyMethodService->getIdentifyMethodsSettings(),
			'signature_flow' => $this->getSignatureFlow(),
			'docmdp_config' => $this->docMdpConfigService->getConfig(),
			'can_request_sign' => $this->canRequestSign(),
		];
	}

	private function getSignatureFlow(): string {
		return $this->appConfig->getValueString(
			Application::APP_ID,
			'signature_flow',
			\OCA\Libresign\Enum\SignatureFlow::NONE->value
		);
	}

	private function canRequestSign(): bool {
		try {
			$this->validateHelper->canRequestSign($this->userSession->getUser());
			return true;
		} catch (LibresignException) {
			return false;
		}
	}
}
