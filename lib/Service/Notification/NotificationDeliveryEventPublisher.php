<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Events\NotificationDeliveryStatusChangedEvent;
use OCP\EventDispatcher\IEventDispatcher;
use Psr\Log\LoggerInterface;

class NotificationDeliveryEventPublisher {
	public function __construct(
		private NotificationDeliveryMapper $deliveries,
		private FileMapper $files,
		private IEventDispatcher $events,
		private LoggerInterface $logger,
	) {
	}

	public function publish(int $deliveryId): void {
		try {
			$delivery = $this->deliveries->findById($deliveryId);
			$file = $this->files->getById($delivery->getFileId());
			$root = $file->hasParent()
				? $this->files->getById($file->getParentFileId())
				: $file;
			$event = NotificationDeliveryStatusChangedEvent::fromPersistedDelivery($root, $delivery);
			if ($event !== null) {
				$this->events->dispatchTyped($event);
			}
		} catch (\Throwable $e) {
			// The delivery has already been committed: webhook listeners are best effort.
			$this->logger->warning('Unable to publish persisted notification outcome', [
				'deliveryId' => $deliveryId,
				'errorType' => get_debug_type($e),
			]);
		}
	}
}
