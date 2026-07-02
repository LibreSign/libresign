<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @template-implements IEventListener<LoadAdditionalScriptsEvent>
 */
class LoadAdditionalListener implements IEventListener {
	public function __construct(
		private IAppManager $appManager,
		private CertificateEngineFactory $certificateEngineFactory,
		private IInitialState $initialState,
	) {
	}
	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof LoadAdditionalScriptsEvent)) {
			return;
		}

		if (!$this->appManager->isEnabledForUser('libresign')) {
			return;
		}

		if (!$this->certificateEngineFactory->getEngine()->isSetupOk()) {
			return;
		}

		if (class_exists('\OCA\Files\App')) {
			$this->initialState->provideInitialState('certificate_ok', true);
			Util::addInitScript(Application::APP_ID, 'libresign-init');
		}
	}
}
