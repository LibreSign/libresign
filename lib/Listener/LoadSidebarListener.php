<?php

namespace OCA\Libresign\Listener;

use OCA\Files\Event\LoadSidebar;
use OCA\Libresign\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @codeCoverageIgnore
 */
class LoadSidebarListener implements IEventListener {
	public function handle(Event $event): void {
		if (!($event instanceof LoadSidebar)) {
			return;
		}

		if (version_compare(\OC_Util::getVersionString(), '20', '<=')) {
			Util::addScript(Application::APP_ID, 'libresign-tab-20');
		} else {
			Util::addScript(Application::APP_ID, 'libresign-tab');
		}
	}
}
