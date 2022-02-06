<?php

namespace OCA\Libresign\Listener;

use OCA\LibreSign\Event\ASignedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class SignedListener implements IEventListener {
	public function handle(Event $event): void {
		/** @var ASignedEvent */
		if (!($event instanceof ASignedEvent)) {
			return;
		}

		if ($event->allSigned) {
			$event->fileService->notifyCallback($event->signedFile);
		}
	}
}
