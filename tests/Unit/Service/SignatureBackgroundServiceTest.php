<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Service\SignatureBackgroundService;
use OCP\Files\IAppData;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\ITempManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class SignatureBackgroundServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private SignatureBackgroundService $service;
	private IAppConfig $appConfig;
	private IAppData&MockObject $appData;
	private IConfig&MockObject $config;
	private ITempManager&MockObject $tempManager;

	public function setUp(): void {
		$this->appData = $this->createMock(IAppData::class);
		$this->appConfig = $this->getMockAppConfig();
		$this->config = $this->createMock(IConfig::class);
		$this->tempManager = $this->createMock(ITempManager::class);
	}


	private function getClass(): SignatureBackgroundService {
		$this->service = new SignatureBackgroundService(
			$this->appData,
			$this->appConfig,
			$this->config,
			$this->tempManager,
		);
		return $this->service;
	}

	#[DataProvider('providerScaleDimensions')]
	public function testScaleDimensions(
		int $inputWidth,
		int $inputHeight,
		float $configWidth,
		float $configHeight,
		int $expectedWidth,
		int $expectedHeight,
	): void {
		$this->appConfig->setValueFloat('libresign', 'signature_width', $configWidth);
		$this->appConfig->setValueFloat('libresign', 'signature_height', $configHeight);
		$class = $this->getClass();
		$result = self::invokePrivate($class, 'scaleDimensions', [$inputWidth, $inputHeight]);
		$this->assertSame(
			['width' => $expectedWidth, 'height' => $expectedHeight],
			$result
		);
	}

	public static function providerScaleDimensions(): array {
		return [
			'under limit => return equals' =>
				[100, 50, 200, 100, 100, 50],
			'between upscale limit => return equals' =>
				[500, 200, 200, 100, 500, 200],
			'height over upscale limit => reduce to scale limit' =>
				[800, 600, 200, 100, 400, 300],
			'width and height over upscale limit => reduce to scale limit' =>
				[2000, 1600, 200, 100, 375, 300],
			'every return integer' =>
				[2000, 1600, 200.7, 100.5, 376, 301],
		];
	}
}
