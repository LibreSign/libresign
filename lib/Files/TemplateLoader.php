<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Files;

use OCA\Files\Event\LoadSidebar;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use OCP\IUserSession;

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
	) {
	}

	public static function register(IEventDispatcher $dispatcher): void {
		$dispatcher->addServiceListener(LoadSidebar::class, self::class);
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof LoadSidebar)) {
			return;
		}
		$this->initialState->provideInitialState(
			'certificate_ok',
			$this->certificateEngineFactory->getEngine()->isSetupOk()
		);

		$this->initialState->provideInitialState(
			'identify_methods',
			$this->identifyMethodService->getIdentifyMethodsSettings()
		);

		try {
			$this->validateHelper->canRequestSign($this->userSession->getUser());
			$this->initialState->provideInitialState('can_request_sign', true);
		} catch (LibresignException) {
			$this->initialState->provideInitialState('can_request_sign', false);
		}
	}
}
