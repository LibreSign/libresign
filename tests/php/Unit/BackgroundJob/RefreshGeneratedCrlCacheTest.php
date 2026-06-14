<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\BackgroundJob;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\BackgroundJob\RefreshGeneratedCrlCache;
use OCA\Libresign\Service\Crl\CrlService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RefreshGeneratedCrlCacheTest extends TestCase {
	private CrlService&MockObject $crlService;
	private IAppConfig&MockObject $appConfig;
	private ITimeFactory&MockObject $time;
	private LoggerInterface&MockObject $logger;
	private RefreshGeneratedCrlCache $job;

	protected function setUp(): void {
		$this->crlService = $this->createMock(CrlService::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->time = $this->createMock(ITimeFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->job = new RefreshGeneratedCrlCache(
			$this->time,
			$this->crlService,
			$this->appConfig,
			$this->logger,
		);
	}

	public function testRunSkipsRefreshWhenCurrentDayWasAlreadyProcessed(): void {
		$this->mockCurrentTimestamp('2026-06-14 00:05:00');

		$this->appConfig->expects($this->once())
			->method('getValueString')
			->with(Application::APP_ID, 'crl_generated_cache_last_refresh_date', '')
			->willReturn('2026-06-14');

		$this->crlService->expects($this->never())
			->method('refreshGeneratedCrlCache');

		$this->appConfig->expects($this->never())
			->method('setValueString');

		$this->logger->expects($this->never())
			->method('error');

		$this->job->run([]);
	}

	public function testRunRefreshesAndMarksCurrentDayWhenDue(): void {
		$this->mockCurrentTimestamp('2026-06-14 00:05:00');

		$this->appConfig->expects($this->once())
			->method('getValueString')
			->with(Application::APP_ID, 'crl_generated_cache_last_refresh_date', '')
			->willReturn('2026-06-13');

		$this->crlService->expects($this->once())
			->method('refreshGeneratedCrlCache')
			->willReturn(2);

		$this->appConfig->expects($this->once())
			->method('setValueString')
			->with(Application::APP_ID, 'crl_generated_cache_last_refresh_date', '2026-06-14');

		$this->logger->expects($this->never())
			->method('error');

		$this->job->run([]);
	}

	public function testRunLogsErrorAndDoesNotAdvanceMarkerWhenRefreshFails(): void {
		$this->mockCurrentTimestamp('2026-06-14 00:05:00');

		$this->appConfig->expects($this->once())
			->method('getValueString')
			->with(Application::APP_ID, 'crl_generated_cache_last_refresh_date', '')
			->willReturn('');

		$this->crlService->expects($this->once())
			->method('refreshGeneratedCrlCache')
			->willThrowException(new \RuntimeException('boom'));

		$this->appConfig->expects($this->never())
			->method('setValueString');

		$this->logger->expects($this->once())
			->method('error')
			->with(
				'Failed to refresh daily generated CRL cache: {message}',
				$this->callback(static fn (array $context): bool => $context['message'] === 'boom'
					&& $context['exception'] instanceof \RuntimeException)
			);

		$this->job->run([]);
	}

	private function mockCurrentTimestamp(string $dateTime): void {
		$timezone = new \DateTimeZone(date_default_timezone_get());
		$timestamp = (new \DateTimeImmutable($dateTime, $timezone))->getTimestamp();

		$this->time->method('getTime')
			->willReturn($timestamp);
	}
}
