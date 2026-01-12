<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\BackgroundJob;

use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCA\Libresign\Service\SignJobCoordinator;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\AppFramework\Utility\ITimeFactory;
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
		$job = new SignSingleFileJob($this->timeFactory, $this->coordinator);

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
