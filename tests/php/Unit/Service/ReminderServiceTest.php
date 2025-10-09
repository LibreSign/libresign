<?php

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\ReminderService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IAppConfig;
use OCP\IDateTimeZone;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class ReminderServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	protected IJobList|MockObject $jobList;
	protected IAppConfig $appConfig;
	protected IDateTimeZone $dateTimeZone;
	protected ITimeFactory|MockObject $time;
	protected SignRequestMapper|MockObject $signRequestMapper;
	protected IdentifyMethodService|MockObject $identifyMethodService;
	protected LoggerInterface|MockObject $logger;

	public function setUp(): void {
		$this->jobList = $this->createMock(IJobList::class);
		$this->appConfig = $this->getMockAppConfig();
		$this->dateTimeZone = Server::get(IDateTimeZone::class);
		$this->time = $this->createMock(ITimeFactory::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function getService(array $methods = []): ReminderService|MockObject {
		if ($methods) {
			return $this->getMockBuilder(ReminderService::class)
				->setConstructorArgs([
					$this->jobList,
					$this->appConfig,
					$this->dateTimeZone,
					$this->time,
					$this->signRequestMapper,
					$this->identifyMethodService,
					$this->logger,
				])
				->onlyMethods($methods)
				->getMock();
		}
		return new ReminderService(
			$this->jobList,
			$this->appConfig,
			$this->dateTimeZone,
			$this->time,
			$this->signRequestMapper,
			$this->identifyMethodService,
			$this->logger,
		);
	}

	#[DataProvider('providerSummarizeNotifications')]
	public function testSummarizeNotifications(array $notifications, array $expected): void {
		$service = $this->getService();
		$actual = self::invokePrivate($service, 'getNotificationsSummarized', [$notifications]);
		$this->assertEquals($expected, $actual);
	}

	public static function providerSummarizeNotifications(): array {
		$now = (new DateTime())->setTime(12, 0);
		return [
			'empty' => [[], ['first' => null, 'last' => null, 'total' => 0]],
			'only one' => [
				[['date' => (clone $now)->getTimestamp(), 'method' => 'activity']],
				['first' => (clone $now)->setTime(0, 0), 'last' => (clone $now)->setTime(0, 0), 'total' => 1]
			],
			'same day mixed' => [
				[
					['date' => (clone $now)->getTimestamp(), 'method' => 'activity'],
					['date' => (clone $now)->modify('+ 1 hour')->getTimestamp(), 'method' => 'notify'],
					['date' => (clone $now)->modify('- 1 hour')->getTimestamp(), 'method' => 'mail'],
				],
				['first' => (clone $now)->setTime(0, 0), 'last' => (clone $now)->setTime(0, 0), 'total' => 1]
			],
			'two days mixed' => [
				[
					['date' => (clone $now)->getTimestamp(), 'method' => 'activity'],
					['date' => (clone $now)->modify('+ 1 hour')->getTimestamp(), 'method' => 'notify'],
					['date' => (clone $now)->modify('- 1 hour')->getTimestamp(), 'method' => 'mail'],
					['date' => (clone $now)->modify('-2 days')->getTimestamp(), 'method' => 'activity'],
					['date' => (clone $now)->modify('-2 days')->modify('+ 1 hour')->getTimestamp(), 'method' => 'notify'],
					['date' => (clone $now)->modify('-2 days')->modify('- 1 hour')->getTimestamp(), 'method' => 'mail'],
				],
				['first' => (clone $now)->modify('-2 days')->setTime(0, 0), 'last' => (clone $now)->setTime(0, 0), 'total' => 2]
			],
		];
	}

	#[DataProvider('providerWillNotify')]
	public function testWillNotify(array $summarized, \DateTime $now, int $daysBefore, int $daysBetween, int $max, bool $expected): void {
		$service = $this->getService();
		$actual = self::invokePrivate($service, 'willNotify', [$summarized, $now, $daysBefore, $daysBetween, $max]);
		$this->assertEquals($expected, $actual);
	}

	public static function providerWillNotify(): array {
		$now = new DateTime('2025-10-09 12:00:00', new \DateTimeZone('UTC'));

		return [
			'no notifications, should not send with all zero and null' => [
				[
					'first' => null,
					'last' => null,
					'total' => 0,
				],
				'now' => $now, 'daysBefore' => 0, 'daysBetween' => 0, 'max' => 0, false,
			],
			'no notifications, should not send with daysBetween === 0' => [
				[
					'first' => null,
					'last' => null,
					'total' => 0,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 0, 'max' => 0, false,
			],
			'no notifications, should not send with daysBetween > 0' => [
				[
					'first' => null,
					'last' => null,
					'total' => 0,
				],
				'now' => $now, 'daysBefore' => 0, 'daysBetween' => 1, 'max' => 0, false,
			],
			'no notifications, should not send with daysBefore and daysBetween > 0' => [
				[
					'first' => null,
					'last' => null,
					'total' => 0,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 1, 'max' => 0, false,
			],
			'no notification, scheduled for today, between = 0' => [
				[
					'first' => (clone $now)->setTime(0, 0),
					'last' => (clone $now)->setTime(0, 0),
					'total' => 1,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 0, 'max' => 5, false,
			],
			'no notification, scheduled for today, between = 1' => [
				[
					'first' => (clone $now)->setTime(0, 0),
					'last' => (clone $now)->setTime(0, 0),
					'total' => 1,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 1, 'max' => 5, false,
			],
			'no notification, scheduled for yesterday, between = 0' => [
				[
					'first' => (clone $now)->modify('-1 day')->setTime(0, 0),
					'last' => (clone $now)->modify('-1 day')->setTime(0, 0),
					'total' => 1,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 0, 'max' => 5, false,
			],
			'one notification, scheduled for yesterday, between = 1' => [
				[
					'first' => (clone $now)->modify('-1 day')->setTime(0, 0),
					'last' => (clone $now)->modify('-1 day')->setTime(0, 0),
					'total' => 1,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 1, 'max' => 5, true,
			],
			'one notification, should send' => [
				[
					'first' => (clone $now)->modify('-2 days'),
					'last' => (clone $now)->modify('-2 days'),
					'total' => 1,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 1, 'max' => 5, true,
			],
			'one notification, should not send with daysBefore <= 0' => [
				[
					'first' => (clone $now)->modify('-1 day'),
					'last' => (clone $now)->modify('-1 day'),
					'total' => 1,
				],
				'now' => $now, 'daysBefore' => 0, 'daysBetween' => 1, 'max' => 5, false,
			],
			'two notifications, should send with between === 1 and last === 2' => [
				[
					'first' => (clone $now)->modify('-3 days'),
					'last' => (clone $now)->modify('-2 day'),
					'total' => 2,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 1, 'max' => 5, true,
			],
			'two notifications, should not send with max limit reached' => [
				[
					'first' => (clone $now)->modify('-3 days'),
					'last' => (clone $now)->modify('-2 day'),
					'total' => 5,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 1, 'max' => 5, false,
			],
			'two notifications, should send without max limit' => [
				[
					'first' => (clone $now)->modify('-3 days'),
					'last' => (clone $now)->modify('-2 day'),
					'total' => 5,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 1, 'max' => 0, true,
			],
			'two notifications, should not send with daysBetween <= 0' => [
				[
					'first' => (clone $now)->modify('-2 days'),
					'last' => (clone $now)->modify('-1 day'),
					'total' => 2,
				],
				'now' => $now, 'daysBefore' => 0, 'daysBetween' => 0, 'max' => 5, false,
			],
			'one notification, exact daysBefore limit, should notify' => [
				[
					'first' => (clone $now)->modify('-1 day')->setTime(0, 0),
					'last' => (clone $now)->modify('-1 day')->setTime(0, 0),
					'total' => 1,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 1, 'max' => 5, true,
			],
			'two notifications, exact daysBetween limit, should notify' => [
				[
					'first' => (clone $now)->modify('-3 days'),
					'last' => (clone $now)->modify('-2 days')->setTime(0, 0),
					'total' => 2,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 2, 'max' => 5, true,
			],
			'no notifications, valid config but daysBetween = 0' => [
				[
					'first' => null,
					'last' => null,
					'total' => 0,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 0, 'max' => 5, false,
			],
			'inconsistent data: total > 0 but null dates' => [
				[
					'first' => null,
					'last' => null,
					'total' => 1,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 1, 'max' => 5, false,
			],
			'max = 0 means no limit, should send with valid config' => [
				[
					'first' => (clone $now)->modify('-2 days'),
					'last' => (clone $now)->modify('-2 days'),
					'total' => 1,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 1, 'max' => 0, true,
			],
			'max = 0, high total count, should still send' => [
				[
					'first' => (clone $now)->modify('-10 days'),
					'last' => (clone $now)->modify('-2 days'),
					'total' => 100,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 1, 'max' => 0, true,
			],
			'total exceeds max, should not send' => [
				[
					'first' => (clone $now)->modify('-5 days'),
					'last' => (clone $now)->modify('-2 days'),
					'total' => 6,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 1, 'max' => 5, false,
			],
			'notification today, should not send' => [
				[
					'first' => (clone $now)->setTime(0, 0),
					'last' => (clone $now)->setTime(0, 0),
					'total' => 1,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 1, 'max' => 5, false,
			],
			'multiple notifications, insufficient daysBetween' => [
				[
					'first' => (clone $now)->modify('-5 days'),
					'last' => (clone $now)->setTime(0, 0),
					'total' => 3,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 2, 'max' => 5, false,
			],
			'negative daysBefore, should not send' => [
				[
					'first' => (clone $now)->modify('-2 days'),
					'last' => (clone $now)->modify('-2 days'),
					'total' => 1,
				],
				'now' => $now, 'daysBefore' => -1, 'daysBetween' => 1, 'max' => 5, false,
			],
			'negative daysBetween, should not send' => [
				[
					'first' => (clone $now)->modify('-5 days'),
					'last' => (clone $now)->modify('-2 days'),
					'total' => 2,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => -1, 'max' => 5, false,
			],
			'negative max acts as no limit, should send' => [
				[
					'first' => (clone $now)->modify('-3 days'),
					'last' => (clone $now)->modify('-2 days'),
					'total' => 10,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 1, 'max' => -1, true,
			],
		];
	}

	#[DataProvider('providerSave')]
	public function testSave(
		int $daysBefore,
		int $daysBetween,
		int $max,
		string $sendTimer,
		array $expected,
	): void {
		// Setup fixed time for consistent testing
		$fixedTime = new DateTime('2025-10-09 09:00:00', new \DateTimeZone('UTC'));
		$this->time->method('getDateTime')
			->willReturn($fixedTime);

		$service = $this->getService();
		$actual = $service->save($daysBefore, $daysBetween, $max, $sendTimer);
		$this->assertEquals($expected, $actual);

		$keys = [
			'days_before',
			'days_between',
			'max',
		];

		foreach ($keys as $key) {
			$actualConfig = $this->appConfig->getValueInt(Application::APP_ID, 'reminder_' . $key, $actual[$key]);
			$this->assertEquals($actual[$key], $actualConfig);
		}

		$actualConfig = $this->appConfig->getValueString(Application::APP_ID, 'reminder_send_timer', $actual['send_timer']);
		$this->assertEquals($actual['send_timer'], $actualConfig);
	}

	public static function providerSave(): array {
		$now = (new DateTime('2025-10-09 09:00:00', new \DateTimeZone('UTC')));
		return [
			[
				'daysBefore' => 0, 'daysBetween' => 0, 'max' => 0, 'sendTimer' => '',
				'expected' => [
					'days_before' => 0,
					'days_between' => 0,
					'max' => 0,
					'next_run' => null,
					'send_timer' => '',
				],
			],
			[
				'daysBefore' => 0, 'daysBetween' => 0, 'max' => 1, 'sendTimer' => '',
				'expected' => [
					'days_before' => 0,
					'days_between' => 0,
					'max' => 0,
					'next_run' => null,
					'send_timer' => '',
				],
			],
			[
				'daysBefore' => 0, 'daysBetween' => 1, 'max' => 0, 'sendTimer' => '',
				'expected' => [
					'days_before' => 0,
					'days_between' => 0,
					'max' => 0,
					'next_run' => null,
					'send_timer' => '',
				],
			],
			[
				'daysBefore' => 0, 'daysBetween' => 1, 'max' => 1, 'sendTimer' => '',
				'expected' => [
					'days_before' => 0,
					'days_between' => 0,
					'max' => 0,
					'next_run' => null,
					'send_timer' => '',
				],
			],
			[
				'daysBefore' => 1, 'daysBetween' => 0, 'max' => 0, 'sendTimer' => '',
				'expected' => [
					'days_before' => 0,
					'days_between' => 0,
					'max' => 0,
					'next_run' => null,
					'send_timer' => '',
				],
			],
			[
				'daysBefore' => 1, 'daysBetween' => 1, 'max' => 0, 'sendTimer' => '',
				'expected' => [
					'days_before' => 0,
					'days_between' => 0,
					'max' => 0,
					'next_run' => null,
					'send_timer' => '',
				],
			],
			[
				'daysBefore' => 1, 'daysBetween' => 1, 'max' => 1, 'sendTimer' => '',
				'expected' => [
					'days_before' => 1,
					'days_between' => 1,
					'max' => 1,
					'next_run' => (clone $now)->setTime(10, 0),
					'send_timer' => '10:00',
				],
			],
			[
				'daysBefore' => 1, 'daysBetween' => 1, 'max' => 1, 'sendTimer' => '11:05:00', // Invalid timer, need to be HH:mm
				'expected' => [
					'days_before' => 1,
					'days_between' => 1,
					'max' => 1,
					'next_run' => (clone $now)->setTime(10, 0),
					'send_timer' => '10:00',
				],
			],
			[
				'daysBefore' => 1, 'daysBetween' => 1, 'max' => 1, 'sendTimer' => '08:05',
				'expected' => [
					'days_before' => 1,
					'days_between' => 1,
					'max' => 1,
					'next_run' => (clone $now)->modify('+1 day')->setTime(8, 5),
					'send_timer' => '08:05',
				],
			],
			[
				'daysBefore' => 1, 'daysBetween' => 1, 'max' => 1, 'sendTimer' => '11:05',
				'expected' => [
					'days_before' => 1,
					'days_between' => 1,
					'max' => 1,
					'next_run' => (clone $now)->setTime(11, 5),
					'send_timer' => '11:05',
				],
			],
		];
	}
}
