<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Listener;

use OCA\Libresign\AppInfo\Application;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @template-implements IEventListener<BeforeTemplateRenderedEvent>
 */
class LoadEuroofficeBridgeListener implements IEventListener {
	private const SUPPORTED_APPS = ['eurooffice', 'onlyoffice'];

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		$response = $event->getResponse();
		$app = $response->getApp();

		if (!in_array($app, self::SUPPORTED_APPS, true)) {
			return;
		}

		Util::addScript(Application::APP_ID, 'libresign-eurooffice-bridge');
	}
}
