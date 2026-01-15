<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\BackgroundJob;

use OCA\Libresign\Service\SignJobCoordinator;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

/**
 * Queued job to process a single file signing request asynchronously.
 * This allows multiple files in an envelope to be signed in parallel.
 */
class SignSingleFileJob extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private SignJobCoordinator $coordinator,
	) {
		parent::__construct($time);
	}

	/**
	 * @param array|null $argument
	 */
	#[\Override]
	public function run($argument): void {
		// Handle null arguments from Nextcloud background job system
		$argument = is_array($argument) ? $argument : [];
		$this->coordinator->runSignSingleFile($argument);
	}
}
