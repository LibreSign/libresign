<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Listener;

use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\NodeCleanupService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Cache\CacheEntryRemovedEvent;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\File;
use OCP\Files\Folder;

/**
 * @template-implements IEventListener<Event|BeforeNodeDeletedEvent|CacheEntryRemovedEvent>
 */
class BeforeNodeDeletedListener implements IEventListener {
	public function __construct(
		private NodeCleanupService $cleanupService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof BeforeNodeDeletedEvent) {
			$node = $event->getNode();
			if (!$node instanceof File && !$node instanceof Folder) {
				return;
			}
			if ($node instanceof File && !in_array($node->getMimeType(), ValidateHelper::VALID_MIMETIPE)) {
				return;
			}

			$this->cleanupService->deleteAllByNodeId($node->getId());
			return;
		}

		if ($event instanceof CacheEntryRemovedEvent) {
			$this->cleanupService->deleteAllByNodeId($event->getFileId());
		}
	}
}
