<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Listener;

use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Service\SignFileService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/** @template-implements IEventListener<SignedEvent> */
class SignedCallbackListener implements IEventListener {
	public function __construct(
		private SignFileService $signFileService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		/** @var SignedEvent */
		if (!($event instanceof SignedEvent)) {
			return;
		}

		$updatedFields = $event->getLibreSignFile()->getUpdatedFields();
		if (isset($updatedFields['signed']) && $updatedFields['signed'] === true) {
			$this->signFileService->notifyCallback($event->getSignedFile());
		}
	}
}
