<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\BackgroundJob;

use OCA\Libresign\Service\SigningAttemptGuard;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\Job;

/**
 * Unlike QueuedJob, retain the job until the coordinator returns.
 * A crash leaves it reserved for Nextcloud's normal recovery window.
 */
abstract class RecoverableSigningJob extends Job {
	public function __construct(
		ITimeFactory $time,
		private IJobList $jobList,
		private SigningAttemptGuard $attemptGuard,
	) {
		parent::__construct($time);
	}

	protected function executeSigning(array $argument, callable $sign, callable $discardCredentials): void {
		// Nextcloud may use one service object for several job IDs. Preserve our
		// reservation ID before any other IJobList call changes that object.
		$jobId = $this->getId();
		$executed = $this->attemptGuard->execute($argument, $jobId, $sign);
		if (!$executed) {
			$discardCredentials();
		}
		// Remove only after a controlled return, never before signing starts.
		$this->jobList->removeById($jobId);
	}
}
