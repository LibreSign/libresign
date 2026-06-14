<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\BackgroundJob;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Crl\CrlService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class RefreshGeneratedCrlCache extends TimedJob {
	private const LAST_REFRESH_DATE_KEY = 'crl_generated_cache_last_refresh_date';

	public function __construct(
		ITimeFactory $time,
		private CrlService $crlService,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);

		// Check frequently enough to refresh shortly after midnight without
		// coupling the actual refresh time to the app installation time.
		$this->setInterval(60 * 15);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function run($argument): void {
		$currentRefreshDate = $this->getCurrentRefreshDate();
		$lastRefreshDate = $this->appConfig->getValueString(
			Application::APP_ID,
			self::LAST_REFRESH_DATE_KEY,
			''
		);

		if ($lastRefreshDate === $currentRefreshDate) {
			return;
		}

		try {
			$this->crlService->refreshGeneratedCrlCache();
			$this->appConfig->setValueString(
				Application::APP_ID,
				self::LAST_REFRESH_DATE_KEY,
				$currentRefreshDate
			);
		} catch (\Throwable $exception) {
			$this->logger->error('Failed to refresh daily generated CRL cache: {message}', [
				'message' => $exception->getMessage(),
				'exception' => $exception,
			]);
		}
	}

	private function getCurrentRefreshDate(): string {
		return (new \DateTimeImmutable('@' . $this->time->getTime()))
			->setTimezone(new \DateTimeZone(date_default_timezone_get()))
			->format('Y-m-d');
	}
}
