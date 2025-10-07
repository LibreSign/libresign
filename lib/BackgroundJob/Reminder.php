<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\BackgroundJob;

use OCA\Libresign\Service\ReminderService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

class Reminder extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		protected ReminderService $reminderService,
	) {
		parent::__construct($time);

		// Every day
		$this->setInterval(60 * 60 * 24);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function run($argument): void {
		$this->reminderService->sendReminders();
	}
}
