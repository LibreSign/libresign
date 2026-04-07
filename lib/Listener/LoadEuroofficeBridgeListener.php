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
use OCP\IRequest;
use OCP\Util;

/**
 * @template-implements IEventListener<BeforeTemplateRenderedEvent>
 */
class LoadEuroofficeBridgeListener implements IEventListener {
	public function __construct(
		private IRequest $request,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		$response = $event->getResponse();
		$app = $response->getApp();
		$path = $this->request->getPathInfo() ?? '';

		if ($app !== 'eurooffice' && !str_starts_with($path, '/apps/eurooffice/')) {
			return;
		}

		Util::addScript(Application::APP_ID, 'libresign-eurooffice-bridge');
	}
}
