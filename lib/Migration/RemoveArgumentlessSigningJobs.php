<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCP\BackgroundJob\IJobList;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

final class RemoveArgumentlessSigningJobs implements IRepairStep {
	public function __construct(
		private IJobList $jobList,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'Remove argumentless LibreSign signing jobs';
	}

	#[\Override]
	public function run(IOutput $output): void {
		$this->jobList->remove(SignFileJob::class, null);
		$this->jobList->remove(SignSingleFileJob::class, null);
	}
}
