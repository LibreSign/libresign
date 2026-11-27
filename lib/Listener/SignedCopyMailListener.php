<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Listener;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Events\SigningRequestCompletedEvent;
use OCA\Libresign\Service\SignedCopyMailService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * Queues the signed copy mails once a request is complete. They are sent
 * by the notification delivery job, never from the signing request.
 *
 * @template-implements IEventListener<Event>
 */
final class SignedCopyMailListener implements IEventListener {
	public function __construct(
		private FileMapper $fileMapper,
		private SignedCopyMailService $signedCopyMailService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof SigningRequestCompletedEvent) {
			return;
		}
		try {
			$file = $this->fileMapper->getByUuid($event->getRequestUuid());
		} catch (DoesNotExistException) {
			return;
		}
		$this->signedCopyMailService->queue($file);
	}
}
