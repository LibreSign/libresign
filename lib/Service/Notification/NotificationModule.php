<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * One independent notification integration, with its own supported events.
 *
 * To add a channel, implement this interface and register the service once in
 * Application. Routing and the other channels do not need any changes.
 *
 * @template-extends IEventListener<Event>
 */
interface NotificationModule extends IEventListener {
	/** @return list<class-string<Event>> */
	public static function supportedEvents(): array;

	/** The transport used by this module for HTTP and background delivery. */
	public function transport(): NotificationChannel;
}
