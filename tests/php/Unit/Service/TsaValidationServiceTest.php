<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\TsaValidationService;
use OCP\IAppConfig;

/**
 * @internal
 */
final class TsaValidationServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private TsaValidationService $service;
	private IAppConfig $appConfig;

	public function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->service = new TsaValidationService($this->appConfig);
	}

	public static function provideValidTsaUrls(): array {
		return [
			'no TSA configured' => [''],
			'valid URL' => ['https://freetsa.org/tsr'],
			'localhost URL' => ['http://localhost:8080/tsa'],
		];
	}

	/**
	 * @dataProvider provideValidTsaUrls
	 */
	public function testValidateConfigurationSuccess(string $tsaUrl): void {
		$this->appConfig->setValueString(Application::APP_ID, 'tsa_url', $tsaUrl);
		$this->service->validateConfiguration();
		$this->assertTrue(true);
	}

	public static function provideInvalidTsaUrls(): array {
		return [
			'invalid URL format' => [
				'not-a-valid-url',
				'Invalid TSA URL format: not-a-valid-url',
				false,
			],
			'missing host' => [
				'http://',
				'Invalid TSA URL format: http://',
				false,
			],
			'unresolvable host' => [
				'https://invalid-tsa-server.example.com/tsr',
				'/Timestamp Authority \(TSA\) service is unavailable or misconfigured/',
				true,
			],
		];
	}

	/**
	 * @dataProvider provideInvalidTsaUrls
	 */
	public function testValidateConfigurationFailure(string $tsaUrl, string $expectedMessage, bool $isRegex): void {
		$this->appConfig->setValueString(Application::APP_ID, 'tsa_url', $tsaUrl);

		$this->expectException(LibresignException::class);
		if ($isRegex) {
			$this->expectExceptionMessageMatches($expectedMessage);
		} else {
			$this->expectExceptionMessage($expectedMessage);
		}

		$this->service->validateConfiguration();
	}
}
