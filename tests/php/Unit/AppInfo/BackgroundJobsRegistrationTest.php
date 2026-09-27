<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\AppInfo;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use PHPUnit\Framework\TestCase;

final class BackgroundJobsRegistrationTest extends TestCase {
	public function testParameterizedSigningJobsAreNotRegisteredWithoutArguments(): void {
		$infoXml = simplexml_load_file(dirname(__DIR__, 3) . '/appinfo/info.xml');
		$this->assertNotFalse($infoXml);

		$jobs = array_map(
			static fn ($job): string => (string)$job,
			iterator_to_array($infoXml->{'background-jobs'}->job),
		);

		$this->assertNotContains(SignFileJob::class, $jobs);
		$this->assertNotContains(SignSingleFileJob::class, $jobs);
	}
}
