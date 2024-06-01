<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OC\IntegrityCheck\Helpers\FileAccessHelper;
use OCA\Libresign\Service\Install\SignFiles;
use OCP\App\IAppManager;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;

final class SignFilesTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private FileAccessHelper&MockObject $fileAccessHelper;
	private IConfig&MockObject $config;
	private IAppDataFactory&MockObject $appDataFactory;
	private IAppManager&MockObject $appManager;

	public function setUp(): void {
		$this->fileAccessHelper = $this->createMock(FileAccessHelper::class);
		$this->config = $this->createMock(IConfig::class);
		$this->appDataFactory = $this->createMock(IAppDataFactory::class);
		$this->appManager = $this->createMock(IAppManager::class);
	}

	private function getService(): SignFiles{
		return new SignFiles(
			$this->fileAccessHelper,
			$this->config,
			$this->appDataFactory,
			$this->appManager
		);
	}

	/**
	 * @dataProvider dataGetArchitectures
	 */
	public function testGetArchitectures(array $appInfo, bool $throwException, $expected):void {
		$this->appManager->method('getAppInfo')
			->willReturn($appInfo);
		if ($throwException) {
			$this->expectExceptionMessage('dependencies>architecture not found at info.xml');
		}
		$actual = $this->getService()->getArchitectures();
		if ($throwException) {
			return;
		}
		$this->assertEquals($expected, $actual);
	}

	public static function dataGetArchitectures(): array {
		return [
			[[], true, []],
			[['dependencies' => ['architecture' => []]], true, []],
			[['dependencies' => ['architecture' => ['x86_64']]], false, ['x86_64']],
			[['dependencies' => ['architecture' => ['x86_64', 'aarch64']]], false, ['x86_64', 'aarch64']],
		];
	}
}
