<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\BackgroundJob;

use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCA\Libresign\Service\SigningAttemptGuard;
use OCA\Libresign\Service\SignJobCoordinator;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\MockObject\MockObject;

class SignSingleFileJobTest extends TestCase {
	private ITimeFactory&MockObject $timeFactory;
	private SignJobCoordinator&MockObject $coordinator;

	public function setUp(): void {
		parent::setUp();
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->coordinator = $this->createMock(SignJobCoordinator::class);
	}

	public function testRunDelegatesToCoordinator(): void {
		$jobList = $this->createMock(IJobList::class);
		$attemptGuard = $this->createMock(SigningAttemptGuard::class);
		$job = new SignSingleFileJob($this->timeFactory, $this->coordinator, $jobList, $attemptGuard);
		$job->setId('42');
		$jobList->expects($this->once())->method('removeById')->with('42');
		$attemptGuard->method('execute')->willReturnCallback(static function (array $argument, string $id, callable $run): bool {
			$run();
			return true;
		});

		$arguments = [
			'fileId' => 1,
			'signRequestId' => 2,
			'userId' => 'user',
			'credentialsId' => 'cred',
		];

		$this->coordinator->expects($this->once())
			->method('runSignSingleFile')
			->with($arguments);

		$job->run($arguments);
	}
}
