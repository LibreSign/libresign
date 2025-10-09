<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use DateMalformedStringException;
use DateTime;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\BackgroundJob\Reminder;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IAppConfig;
use OCP\IDateTimeZone;
use Psr\Log\LoggerInterface;

class ReminderService {
	public function __construct(
		protected IJobList $jobList,
		protected IAppConfig $appConfig,
		protected IDateTimeZone $dateTimeZone,
		protected ITimeFactory $time,
		protected SignRequestMapper $signRequestMapper,
		protected IdentifyMethodService $identifyMethodService,
		protected LoggerInterface $logger,
	) {
	}

	public function getSettings(): array {
		$settings = [
			'days_before' => $this->appConfig->getValueInt(Application::APP_ID, 'reminder_days_before', 0),
			'days_between' => $this->appConfig->getValueInt(Application::APP_ID, 'reminder_days_between', 0),
			'max' => $this->appConfig->getValueInt(Application::APP_ID, 'reminder_max', 0),
			'send_timer' => $this->appConfig->getValueString(Application::APP_ID, 'reminder_send_timer', '10:00'),
			'next_run' => null,
		];
		foreach ($this->jobList->getJobsIterator(Reminder::class, 1, 0) as $job) {
			$details = $this->jobList->getDetailsById($job->getId());
			$settings['next_run'] = new \DateTime('@' . $details['last_checked'], new \DateTimeZone('UTC'));
		}
		return $settings;
	}

	public function save(
		int $daysBefore,
		int $daysBetween,
		int $max,
		string $sendTimer,
	): array {
		$config = $this->saveConfig($daysBefore, $daysBetween, $max, $sendTimer);
		$config['next_run'] = $this->scheduleJob($config['send_timer']);
		return $config;
	}

	protected function saveConfig(
		int $daysBefore,
		int $daysBetween,
		int $max,
		string $sendTimer,
	): array {
		if ($daysBetween <= 0
			|| $daysBefore <= 0
			|| $max <= 0
		) {
			$this->appConfig->deleteKey(Application::APP_ID, 'reminder_days_before');
			$this->appConfig->deleteKey(Application::APP_ID, 'reminder_days_between');
			$this->appConfig->deleteKey(Application::APP_ID, 'reminder_max');
			$this->appConfig->deleteKey(Application::APP_ID, 'reminder_send_timer');
			return [
				'days_before' => 0,
				'days_between' => 0,
				'max' => 0,
				'send_timer' => '',
			];
		}

		$sendTimer = $this->normalizeTime($sendTimer);

		$this->setIfChangedInt('reminder_days_before', $daysBefore);
		$this->setIfChangedInt('reminder_days_between', $daysBetween);
		$this->setIfChangedInt('reminder_max', $max);
		$this->setIfChangedString('reminder_send_timer', $sendTimer);

		return [
			'days_before' => $daysBefore,
			'days_between' => $daysBetween,
			'max' => $max,
			'send_timer' => $sendTimer,
		];
	}

	private function normalizeTime(string $time): string {
		if (!$time || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
			return '10:00';
		}
		return $time;
	}

	private function setIfChangedInt(string $key, int $value, int $default = 0): void {
		$prev = $this->appConfig->getValueInt(Application::APP_ID, $key, $default);
		if ($prev !== $value) {
			$this->appConfig->setValueInt(Application::APP_ID, $key, $value);
		}
	}

	private function setIfChangedString(string $key, string $value, string $default = ''): void {
		$prev = $this->appConfig->getValueString(Application::APP_ID, $key, $default);
		if ($prev !== $value) {
			$this->appConfig->setValueString(Application::APP_ID, $key, $value);
		}
	}

	protected function scheduleJob(string $startTime): ?DateTime {
		$this->jobList->remove(
			Reminder::class,
		);

		if ($startTime === '') {
			return null;
		}
		$runAfter = $this->getStartTime($startTime);
		if (!$runAfter) {
			return null;
		}

		$this->jobList->scheduleAfter(
			Reminder::class,
			$runAfter->getTimestamp(),
		);
		return $runAfter;
	}

	protected function getStartTime(string $startTime): ?\DateTime {
		$timezone = $this->dateTimeZone->getTimeZone();

		$now = $this->time->getDateTime('now', new \DateTimeZone('UTC'));
		$dateTime = clone $now;

		try {
			$time = new \DateTime($startTime, $timezone);
		} catch (DateMalformedStringException $e) {
			$this->logger->error('Failed to parse reminder send time: ' . $e->getMessage());
			return null;
		}
		// 'G' = 24-hour format hour (no leading zeros),
		// 'i' = minutes with leading zeros
		$dateTime->setTime((int)$time->format('G'), (int)$time->format('i'));
		$dateTime->setTimezone(new \DateTimeZone('UTC'));
		if ($dateTime <= $now) {
			$dateTime->modify('+1 day');
		}

		return $dateTime;
	}

	public function sendReminders(): void {
		$daysBefore = $this->appConfig->getValueInt(Application::APP_ID, 'reminder_days_before', 0);
		if ($daysBefore <= 0) {
			return;
		}
		$daysBetween = $this->appConfig->getValueInt(Application::APP_ID, 'reminder_days_between', 0);
		if ($daysBetween <= 0) {
			return;
		}
		$max = $this->appConfig->getValueInt(Application::APP_ID, 'reminder_max', 0);
		if ($max === 0) {
			return;
		}

		foreach ($this->fetchJob($daysBefore, $daysBetween, $max) as $job) {
			$job->notify();
		}
	}

	/**
	 * @return \Generator<IIdentifyMethod>
	 */
	protected function fetchJob(int $daysBefore, int $daysBetween, int $max): \Generator {
		$now = $this->time->getDateTime('now', $this->dateTimeZone->getTimeZone());
		foreach ($this->signRequestMapper->findRemindersCandidates() as $entityIdentifyMethod) {
			$signRequest = $this->signRequestMapper->getById($entityIdentifyMethod->getSignRequestId());

			$metadata = $signRequest->getMetadata();
			$summarized = $this->getNotificationsSummarized($metadata['notify'] ?? []);
			if (!$this->willNotify($summarized, $now, $daysBefore, $daysBetween, $max)) {
				continue;
			}

			$this->identifyMethodService->setCurrentIdentifyMethod($entityIdentifyMethod);
			$identifyMethod = $this->identifyMethodService->getInstanceOfIdentifyMethod(
				$entityIdentifyMethod->getIdentifierKey(),
				$entityIdentifyMethod->getIdentifierValue(),
			);
			yield $identifyMethod;
		};
	}

	protected function willNotify(array $summarized, \DateTime $now, int $daysBefore, int $daysBetween, int $max): bool {
		if ($this->isMaxReached($summarized['total'], $max)) {
			return false;
		}

		if ($daysBetween === 0) {
			return false;
		}

		if ($summarized['total'] === 1) {
			return $this->shouldNotifyAfterSomeDays($summarized['first'], $now, $daysBefore);
		}
		return $this->shouldNotifyAfterSomeDays($summarized['last'], $now, $daysBetween);
	}


	private function isMaxReached(int $total, int $max): bool {
		return $max > 0 && $total >= $max;
	}

	private function shouldNotifyAfterSomeDays(?\DateTime $date, \DateTime $now, int $maxDays): bool {
		$daysAfter = $date?->diff($now)?->days ?? 0;
		return $maxDays > 0 && $daysAfter >= $maxDays;
	}

	protected function getNotificationsSummarized(array $notifications): array {
		if (empty($notifications)) {
			return [
				'first' => null,
				'last' => null,
				'total' => 0,
			];
		}
		$dates = [];
		$timeZone = new \DateTimeZone('UTC');
		foreach ($notifications as $notification) {
			$dateTime = new \DateTime('@' . $notification['date'], $timeZone);
			$dateTime->setTime(0, 0, 0);
			$dates[$dateTime->format('Y-m-d')] = $dateTime;
		}
		$dates = array_values($dates);
		usort($dates, fn ($a, $b) => $a <=> $b);
		return [
			'first' => $dates[0],
			'last' => end($dates),
			'total' => count($dates),
		];
	}
}
