<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use DateTime;
use OCA\Libresign\BackgroundJob\Reminder;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\Reminder\ReminderPolicy;
use OCA\Libresign\Service\Policy\Provider\Reminder\ReminderPolicyValue;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IDateTimeZone;
use Psr\Log\LoggerInterface;

class ReminderService {
	public function __construct(
		protected IJobList $jobList,
		protected PolicyService $policyService,
		protected IDateTimeZone $dateTimeZone,
		protected ITimeFactory $time,
		protected SignRequestMapper $signRequestMapper,
		protected IdentifyMethodService $identifyMethodService,
		protected LoggerInterface $logger,
	) {
	}

	public function getSettings(): array {
		$settings = $this->getEffectiveSettings();
		$settings['next_run'] = null;
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
			$normalized = [
				'days_before' => 0,
				'days_between' => 0,
				'max' => 0,
				'send_timer' => '',
			];

			$this->saveSystemSettings($normalized);
			return $normalized;
		}

		$sendTimer = $this->normalizeTime($sendTimer);

		$normalized = [
			'days_before' => $daysBefore,
			'days_between' => $daysBetween,
			'max' => $max,
			'send_timer' => $sendTimer,
		];

		$this->saveSystemSettings($normalized);
		return $normalized;
	}

	private function normalizeTime(string $time): string {
		if (!$time || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
			return '10:00';
		}
		return $time;
	}

	/** @return array{days_before: int, days_between: int, max: int, send_timer: string} */
	private function getEffectiveSettings(): array {
		$resolvedValue = $this->policyService->resolve(ReminderPolicy::KEY)->getEffectiveValue();
		return ReminderPolicyValue::normalize($resolvedValue);
	}

	/** @param array{days_before: int, days_between: int, max: int, send_timer: string} $settings */
	private function saveSystemSettings(array $settings): void {
		$allowChildOverride = $this->policyService->getSystemPolicy(ReminderPolicy::KEY)?->isAllowChildOverride() ?? false;
		$this->policyService->saveSystem(
			ReminderPolicy::KEY,
			ReminderPolicyValue::encode($settings),
			$allowChildOverride,
		);
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
		} catch (\Exception $e) {
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
		$settings = $this->getEffectiveSettings();

		$daysBefore = $settings['days_before'];
		if ($daysBefore <= 0) {
			return;
		}

		$daysBetween = $settings['days_between'];
		if ($daysBetween <= 0) {
			return;
		}

		$max = $settings['max'];
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
			try {
				$identifyMethod = $this->identifyMethodService->getInstanceOfIdentifyMethod(
					$entityIdentifyMethod->getIdentifierKey(),
					$entityIdentifyMethod->getIdentifierValue(),
				);
			} catch (LibresignException $e) {
				$this->logger->error('Failed to get instance of identify method', [
					'error' => $e->getMessage(),
					'identifier_key' => $entityIdentifyMethod->getIdentifierKey(),
					'identifier_value' => $entityIdentifyMethod->getIdentifierValue(),
					'sign_request_id' => $entityIdentifyMethod->getSignRequestId(),
					'metadata' => $metadata,
				]);
				continue;
			}
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
