<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\BackgroundJob\SendReminders;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IAppConfig;
use OCP\IDateTimeZone;

class ReminderService {
	public function __construct(
		protected IJobList $jobList,
		protected IAppConfig $appConfig,
		protected IDateTimeZone $dateTimeZone,
		protected ITimeFactory $time,
		protected SignRequestMapper $signRequestMapper,
		protected IdentifyMethodService $identifyMethodService,
	) {
	}

	public function getSettings(): array {
		return [
			'days_before' => $this->appConfig->getValueInt(Application::APP_ID, 'reminder_days_before', 0),
			'days_between' => $this->appConfig->getValueInt(Application::APP_ID, 'reminder_days_between', 0),
			'max' => $this->appConfig->getValueInt(Application::APP_ID, 'reminder_max', 0),
			'send_timer' => $this->appConfig->getValueString(Application::APP_ID, 'reminder_send_timer', '10:00'),
		];
	}

	public function save(
		int $daysBefore = 1,
		int $daysBetween = 1,
		?int $max = 5,
		string $sendTimer = '10:00',
	): array {
		$return = $this->saveConfig($daysBefore, $daysBetween, $max, $sendTimer);
		$this->scheduleJob($return['send_timer']);
		return $return;
	}

	protected function saveConfig(
		int $daysBefore = 1,
		int $daysBetween = 1,
		int $max = 5,
		string $sendTimer = '10:00',
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

		$previous = $this->appConfig->getValueInt(Application::APP_ID, 'reminder_days_before', 0);
		if ($previous !== $daysBefore) {
			$this->appConfig->setValueInt(Application::APP_ID, 'reminder_days_before', $daysBefore);
		}

		$previous = $this->appConfig->getValueInt(Application::APP_ID, 'reminder_days_between', 0);
		if ($previous !== $daysBetween) {
			$this->appConfig->setValueInt(Application::APP_ID, 'reminder_days_between', $daysBetween);
		}

		$previous = $this->appConfig->getValueInt(Application::APP_ID, 'reminder_max', 0);
		if ($previous !== $max) {
			$this->appConfig->setValueInt(Application::APP_ID, 'reminder_max', $max);
		}

		$previous = $this->appConfig->getValueString(Application::APP_ID, 'reminder_send_timer', '');
		if ($previous !== $sendTimer) {
			if (!$sendTimer || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $sendTimer)) {
				$sendTimer = '10:00';
			}
			$this->appConfig->setValueString(Application::APP_ID, 'reminder_send_timer', $sendTimer);
		}

		return [
			'days_before' => $daysBefore,
			'days_between' => $daysBetween,
			'max' => $max,
			'send_timer' => $sendTimer,
		];
	}

	protected function scheduleJob(string $startTime): void {
		$this->jobList->remove(
			SendReminders::class,
		);

		$runAfter = $this->getStartTime($startTime);
		if (!$runAfter) {
			return;
		}

		$this->jobList->scheduleAfter(
			SendReminders::class,
			$runAfter->getTimestamp(),
		);
	}

	protected function getStartTime(string $startTime): ?\DateTime {
		$timezone = $this->dateTimeZone->getTimeZone();

		$dateTime = new \DateTime($startTime, $timezone);
		$dateTime->setTimezone(new \DateTimeZone('UTC'));

		$now = new \DateTime('now', new \DateTimeZone('UTC'));
		if ($dateTime > $now) {
			return $dateTime;
		}
		$dateTime->modify('+1 day');

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
		return $daysAfter > $maxDays;
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
