<?php
/*
 * @copyright Copyright (c) 2022 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Files;

use OCA\Files\Event\LoadSidebar;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\SignatureService;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use OCP\IUserSession;

class TemplateLoader implements IEventListener {
	public function __construct(
		private IRequest $request,
		private IUserSession $userSession,
		private AccountService $accountService,
		private IInitialState $initialState,
		private SignatureService $signatureService,
	) {
		$this->initialState = $initialState;
		$this->signatureService = $signatureService;
	}

	public static function register(IEventDispatcher $dispatcher): void {
		$dispatcher->addServiceListener(LoadSidebar::class, self::class);
	}

	public function handle(Event $event): void {
		if (!($event instanceof LoadSidebar)) {
			return;
		}
		$this->initialState->provideInitialState(
			'certificate_ok',
			$this->signatureService->hasRootCert()
		);

		$this->initialState->provideInitialState('config', $this->accountService->getConfig(
			'file_user_uuid',
			$this->request->getParam('uuid'),
			$this->userSession->getUser(),
			'url'
		));
	}
}
