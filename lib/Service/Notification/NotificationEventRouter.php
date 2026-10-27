<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Exception\NotificationTransportException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/**
 * Deliver each event to independent channel handlers.
 *
 * To add a channel, register a handler in Application. No existing handler or
 * ledger code needs to know its transport. Channel exceptions are reported
 * independently. Invariant violations and PHP errors remain visible.
 *
 * @template-implements IEventListener<Event>
 */
final class NotificationEventRouter implements IEventListener {
	/**
	 * Convert a list of channel modules into supported-event routing.
	 *
	 * @param list<NotificationModule> $modules
	 */
	public static function fromModules(array $modules, LoggerInterface $logger, ?IDBConnection $db = null): self {
		$handlers = [];
		foreach ($modules as $module) {
			foreach ($module::supportedEvents() as $eventClass) {
				if (!is_a($eventClass, Event::class, true)) {
					throw new \InvalidArgumentException('Invalid notification module event');
				}
				$handlers[$eventClass][] = $module;
			}
		}
		return new self($handlers, $logger, $db);
	}

	/** @param array<class-string<Event>, list<IEventListener<Event>>> $handlersByEvent */
	public function __construct(
		private array $handlersByEvent,
		private LoggerInterface $logger,
		private ?IDBConnection $db = null,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		$firstFailure = null;
		$programmingFailure = null;
		foreach ($this->handlersByEvent[$event::class] ?? [] as $handler) {
			try {
				$handler->handle($event);
			} catch (\Exception $exception) {
				$firstFailure ??= $exception;
				if ($exception instanceof \LogicException) {
					$programmingFailure ??= $exception;
				}
				$this->logger->warning('A notification channel did not confirm submission', [
					'handler' => get_debug_type($handler),
					'exceptionType' => get_debug_type($exception),
				]);
			}
		}

		// A domain mutation may commit only when every intended channel has
		// been durably recorded. An ordinary channel failure cannot be ignored
		// while the database transaction is still open.
		if ($firstFailure !== null && $this->db?->inTransaction()) {
			throw new \LogicException('Unable to register notifications atomically', 0, $firstFailure);
		}

		// Do not mask invariant violations as an ordinary transport problem.
		// Other channels were still attempted first.
		if ($programmingFailure !== null) {
			throw $programmingFailure;
		}

		// A manual request must report an incomplete result, but only AFTER
		// the remaining channels have had a chance to submit independently.
		if ($firstFailure !== null
			&& $event instanceof SendSignNotificationEvent
			&& str_starts_with($event->getDeliveryEventId(), 'manual:')) {
			// Preserve domain validation and wrapped SMTP failure semantics:
			// the existing controller can distinguish them by cause.
			if ($firstFailure instanceof LibresignException) {
				throw $firstFailure;
			}
			throw new NotificationTransportException(
				'One or more notification channels did not confirm submission',
				NotificationDeliveryState::UNKNOWN,
				'notification_channels_not_confirmed',
				$firstFailure,
			);
		}
	}
}
