<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\BackgroundJob;

use OCA\Libresign\Service\SigningAttemptGuard;
use OCA\Libresign\Service\SignJobCoordinator;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;

/** Background job to process file signing asynchronously. */
class SignFileJob extends RecoverableSigningJob {
	public function __construct(
		ITimeFactory $time,
		private SignJobCoordinator $coordinator,
		IJobList $jobList,
		SigningAttemptGuard $attemptGuard,
	) {
		parent::__construct($time, $jobList, $attemptGuard);
	}

	/** @param array|null $argument */
	#[\Override]
	public function run($argument): void {
		$argument = is_array($argument) ? $argument : [];
		$this->executeSigning(
			$argument,
			fn () => $this->coordinator->runSignFile($argument),
			fn () => $this->coordinator->discardJobCredentials($argument),
		);
	}
}
