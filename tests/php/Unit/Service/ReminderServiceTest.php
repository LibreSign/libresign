<?php

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

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

final class ReminderServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	protected IJobList|MockObject $jobList;
	protected IAppConfig $appConfig;
	protected IDateTimeZone $dateTimeZone;
	protected ITimeFactory $time;
	protected SignRequestMapper|MockObject $signRequestMapper;
	protected IdentifyMethodService|MockObject $identifyMethodService;

	public function setUp(): void {
		$this->jobList = $this->createMock(IJobList::class);
		$this->appConfig = Server::get(IAppConfig::class);
		$this->dateTimeZone = Server::get(IDateTimeZone::class);
		$this->time = Server::get(ITimeFactory::class);
		$this->signRequestMapper = $this->createMock(SignRequestMapper::class);
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
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
		$now = (new DateTime())->setTime(12, 0);

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
			'one notification, should send' => [
				[
					'first' => (clone $now)->modify('-2 days'),
					'last' => (clone $now)->modify('-2 days'),
					'total' => 1,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 0, 'max' => 5, true,
			],
			'one notification, should not send with daysBefore <= 0' => [
				[
					'first' => (clone $now)->modify('-1 day'),
					'last' => (clone $now)->modify('-1 day'),
					'total' => 1,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 0, 'max' => 5, false,
			],
			'two notifications, should not send with between === 1 and last === 1' => [
				[
					'first' => (clone $now)->modify('-3 days'),
					'last' => (clone $now)->modify('-1 day'),
					'total' => 2,
				],
				'now' => $now, 'daysBefore' => 1, 'daysBetween' => 1, 'max' => 5, false,
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
				'now' => $now, 'daysBefore' => 0, 'daysBetween' => 1, 'max' => 5, false,
			],
		];
	}
}
