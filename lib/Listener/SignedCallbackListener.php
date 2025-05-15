<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Listener;

use OCA\Libresign\Events\ASignedCallbackEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/** @template-implements IEventListener<ASignedCallbackEvent> */
class SignedCallbackListener implements IEventListener {
	public function handle(Event $event): void {
		/** @var ASignedCallbackEvent */
		if (!($event instanceof ASignedCallbackEvent)) {
			return;
		}

		if ($event->allSigned) {
			$event->fileService->notifyCallback($event->signedFile);
		}
	}
}
