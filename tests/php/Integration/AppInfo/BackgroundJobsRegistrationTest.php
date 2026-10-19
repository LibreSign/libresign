<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\AppInfo;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCP\App\IAppManager;
use OCP\Server;
use PHPUnit\Framework\TestCase;

final class BackgroundJobsRegistrationTest extends TestCase {
	public function testParameterizedSigningJobsAreNotRegisteredWithoutArguments(): void {
		$infoXmlPath = realpath(__DIR__ . '/../../../../appinfo/info.xml');
		$this->assertNotFalse($infoXmlPath, 'appinfo/info.xml must exist');

		$appInfo = Server::get(IAppManager::class)->getAppInfoByPath($infoXmlPath);
		$this->assertIsArray($appInfo, 'appinfo/info.xml must be parseable by Nextcloud');
		$jobs = $appInfo['background-jobs'];

		$this->assertNotContains(SignFileJob::class, $jobs);
		$this->assertNotContains(SignSingleFileJob::class, $jobs);
	}
}
