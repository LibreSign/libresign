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
use PHPUnit\Framework\Attributes\DataProvider;

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
			'valid HTTPS URL' => ['https://freetsa.org/tsr'],
			'localhost IP with HTTPS' => ['https://127.0.0.1:8080/tsa'],
			'localhost hostname' => ['http://localhost:8080/tsa'],
			'HTTPS with custom port' => ['https://localhost:8443/api/v1/tsa/timestamp'],
			'HTTPS with path' => ['https://localhost:8080/api/v1/tsa/timestamp'],
		];
	}

	#[DataProvider('provideValidTsaUrls')]
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
			'whitespace only' => [
				'   ',
				'Invalid TSA URL format',
				false,
			],
			'javascript protocol' => [
				'javascript:alert(1)',
				'TSA URL must use HTTP or HTTPS protocol',
				false,
			],
			'file protocol' => [
				'file:///etc/passwd',
				'TSA URL must use HTTP or HTTPS protocol',
				false,
			],
			'ftp protocol' => [
				'ftp://tsa.example.com/tsr',
				'TSA URL must use HTTP or HTTPS protocol',
				false,
			],
			'unresolvable host' => [
				'https://invalid-tsa-server-abc123xyz.example.com/tsr',
				'/Timestamp Authority \(TSA\) service is unavailable or misconfigured/',
				true,
			],
		];
	}

	#[DataProvider('provideInvalidTsaUrls')]
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

	public function testGetTsaUrlRetrievesConfiguredValue(): void {
		$this->appConfig->setValueString(Application::APP_ID, 'tsa_url', 'https://test-tsa.example.com');

		$result = self::invokePrivate($this->service, 'getTsaUrl');

		$this->assertSame('https://test-tsa.example.com', $result);
	}

	public static function provideUrlsForFormatValidation(): array {
		return [
			'valid HTTPS' => ['https://freetsa.org/tsr', true],
			'valid HTTP' => ['http://localhost:8080/tsa', true],
			'invalid protocol (not)' => ['not://a//url', false],
			'invalid protocol (ftp)' => ['ftp://tsa.example.com/tsr', false],
			'invalid protocol (file)' => ['file:///etc/passwd', false],
			'invalid protocol (javascript)' => ['javascript:alert(1)', false],
			'missing scheme' => ['://no-scheme.com', false],
			'only domain' => ['example.com', false],
		];
	}

	#[DataProvider('provideUrlsForFormatValidation')]
	public function testValidateTsaUrlFormat(string $url, bool $shouldPass): void {
		if (!$shouldPass) {
			$this->expectException(LibresignException::class);
			$this->expectExceptionMessageMatches('/(Invalid TSA URL|TSA URL must use HTTP or HTTPS protocol)/');
		}

		self::invokePrivate($this->service, 'validateTsaUrlFormat', [$url]);

		if ($shouldPass) {
			$this->assertTrue(true);
		}
	}
}
