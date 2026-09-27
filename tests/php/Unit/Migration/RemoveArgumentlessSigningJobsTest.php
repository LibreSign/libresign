<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Migration;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCA\Libresign\Migration\RemoveArgumentlessSigningJobs;
use OCP\BackgroundJob\IJobList;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

final class RemoveArgumentlessSigningJobsTest extends TestCase {
	public function testRemovesOnlyArgumentlessSigningJobs(): void {
		$jobList = $this->createMock(IJobList::class);
		$output = $this->createMock(IOutput::class);

		$removed = [];
		$jobList->expects($this->exactly(2))
			->method('remove')
			->willReturnCallback(function (string $job, mixed $argument) use (&$removed): void {
				$removed[] = [$job, $argument];
			});

		(new RemoveArgumentlessSigningJobs($jobList))->run($output);

		$this->assertSame([
			[SignFileJob::class, null],
			[SignSingleFileJob::class, null],
		], $removed);
	}
}
