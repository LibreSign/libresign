<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Listener;

use OCA\Libresign\BackgroundJob\UserDeleted;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;

/** @template-implements IEventListener<UserDeletedEvent> */
class UserDeletedListener implements IEventListener {
	public function __construct(
		private IJobList $jobList,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof UserDeletedEvent)) {
			return;
		}
		if (!$event->getUser()->getUID()) {
			return;
		}

		$this->jobList->add(UserDeleted::class, [
			'user_id' => $event->getUser()->getUID(),
			'display_name' => $event->getUser()->getDisplayName(),
		]);
	}
}
