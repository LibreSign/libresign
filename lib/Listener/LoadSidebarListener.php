<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Listener;

use OCA\Files\Event\LoadSidebar;
use OCA\Libresign\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @template-implements IEventListener<Event|LoadSidebar>
 */
class LoadSidebarListener implements IEventListener {
	public function __construct(
		private IAppManager $appManager,
	) {
	}
	public function handle(Event $event): void {
		if (!($event instanceof LoadSidebar)) {
			return;
		}

		if (!$this->appManager->isEnabledForUser('libresign')) {
			return;
		}

		Util::addScript(Application::APP_ID, 'libresign-tab');
		Util::addStyle(Application::APP_ID, 'icons');
	}
}
