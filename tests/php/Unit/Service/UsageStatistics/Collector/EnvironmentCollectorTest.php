<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\UsageStatistics\Collector;

use OCA\Libresign\Service\UsageStatistics\Collector\EnvironmentCollector;
use OCA\Libresign\Service\UsageStatistics\Model\MetricValue;
use OCA\Libresign\Service\UsageStatistics\Model\ReportMode;
use OCA\Libresign\Service\UsageStatistics\Model\ReportPeriod;
use OCA\Libresign\Service\UsageStatistics\Source\RuntimeEnvironment;
use OCA\Libresign\Service\UsageStatistics\UsageStatisticsException;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class EnvironmentCollectorTest extends TestCase {
	private const NOW = 1_790_000_000;

	private IUserManager&MockObject $userManager;
	private ITimeFactory&MockObject $timeFactory;
	private RuntimeEnvironment&MockObject $runtime;

	public function setUp(): void {
		$this->userManager = $this->createMock(IUserManager::class);
		$this->userManager->method('countUsersTotal')->willReturn(42);
		$this->userManager->method('getLastLoggedInUsers')->willReturn([]);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getTime')->willReturn(self::NOW);
		$this->runtime = $this->createMock(RuntimeEnvironment::class);
		$this->runtime->method('nextcloudVersion')->willReturn('36.0.1');
		$this->runtime->method('phpVersion')->willReturn('8.3');
		$this->runtime->method('osFamily')->willReturn('Linux');
		$this->runtime->method('machine')->willReturn('x86_64');
		$this->runtime->method('databasePlatform')->willReturn('mariadb');
	}

	public function testAHistoricalReportKnowsNoEnvironmentValue(): void {
		$this->userManager->expects($this->never())->method('countUsersTotal');
		$this->runtime->expects($this->never())->method('databaseServerVersion');

		$values = $this->collector()->collect($this->period(), ReportMode::HISTORICAL);

		$this->assertSame($this->collector()->metricIds(), array_keys($values));
		foreach ($values as $value) {
			$this->assertFalse($value->isAvailable());
		}
	}

	public function testTheCurrentReportCarriesCoarseEnvironmentValues(): void {
		$this->runtime->method('databaseServerVersion')->willReturn('11.4.3-MariaDB-1:11.4.3+maria~ubu2204');

		$values = $this->values($this->collector()->collect($this->period(), ReportMode::CURRENT));

		$this->assertSame([
			'nextcloud.version' => '36.0.1',
			'nextcloud.users_total' => 42,
			'nextcloud.users_active_30d' => 0,
			'nextcloud.background_job_mode' => 'ajax',
			'php.version' => '8.3',
			'database.type' => 'mariadb',
			'database.version' => '11.4',
			'system.os_family' => 'Linux',
			'system.architecture' => 'x86_64',
		], $values);
	}

	#[DataProvider('databaseVersionsProvider')]
	public function testTheDatabaseVersionKeepsOnlyMajorAndMinor(string $reported, string $expected): void {
		$this->runtime->method('databaseServerVersion')->willReturn($reported);

		$values = $this->collector()->collect($this->period(), ReportMode::CURRENT);

		$this->assertSame($expected, $values['database.version']->value());
	}

	public static function databaseVersionsProvider(): array {
		return [
			'postgres with distribution details' => ['16.4 (Debian 16.4-1.pgdg120+2)', '16.4'],
			'mysql' => ['8.0.39', '8.0'],
			'sqlite' => ['3.46.1', '3.46'],
		];
	}

	public function testAnUnreadableDatabaseVersionFailsInsteadOfGuessing(): void {
		$this->runtime->method('databaseServerVersion')->willReturn('unknown build');

		$this->expectException(UsageStatisticsException::class);

		$this->collector()->collect($this->period(), ReportMode::CURRENT);
	}

	public function testADatabaseWithoutPortableVersionQueryIsUnavailable(): void {
		$this->runtime->method('databaseServerVersion')->willReturn(null);

		$values = $this->collector()->collect($this->period(), ReportMode::CURRENT);

		$this->assertFalse($values['database.version']->isAvailable());
	}

	public function testAUserBackendThatCannotCountMakesTheTotalUnavailable(): void {
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('countUsersTotal')->willReturn(false);
		$userManager->method('getLastLoggedInUsers')->willReturn([]);
		$this->userManager = $userManager;

		$values = $this->collector()->collect($this->period(), ReportMode::CURRENT);

		$this->assertFalse($values['nextcloud.users_total']->isAvailable());
	}

	public function testActiveUsersStopAtTheFirstLoginOutsideTheWindow(): void {
		$lastLogins = [
			'alice' => self::NOW - 3600,
			'bob' => self::NOW - 29 * 86400,
			'carol' => self::NOW - 31 * 86400,
			'dave' => self::NOW - 1,
		];
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('countUsersTotal')->willReturn(4);
		$userManager->method('getLastLoggedInUsers')->willReturn(array_keys($lastLogins));
		$userManager->method('get')->willReturnCallback(function (string $userId) use ($lastLogins): IUser {
			$user = $this->createMock(IUser::class);
			$user->method('getLastLogin')->willReturn($lastLogins[$userId]);
			return $user;
		});
		$this->userManager = $userManager;

		$values = $this->collector()->collect($this->period(), ReportMode::CURRENT);

		$this->assertSame(2, $values['nextcloud.users_active_30d']->value());
	}

	public function testAnUnknownArchitectureIsReportedAsOther(): void {
		$runtime = $this->createMock(RuntimeEnvironment::class);
		$runtime->method('nextcloudVersion')->willReturn('36.0.1');
		$runtime->method('phpVersion')->willReturn('8.3');
		$runtime->method('osFamily')->willReturn('Linux');
		$runtime->method('machine')->willReturn('riscv64');
		$runtime->method('databasePlatform')->willReturn('sqlite');
		$runtime->method('databaseServerVersion')->willReturn('3.46.1');
		$this->runtime = $runtime;

		$values = $this->collector()->collect($this->period(), ReportMode::CURRENT);

		$this->assertSame('other', $values['system.architecture']->value());
	}

	#[DataProvider('backgroundJobModesProvider')]
	public function testTheBackgroundJobModeIsOneOfTheKnownModes(string $stored, string $expected): void {
		$this->runtime->method('databaseServerVersion')->willReturn('8.0.39');
		self::getMockAppConfigWithReset()->setValueString('core', 'backgroundjobs_mode', $stored);

		$values = $this->collector()->collect($this->period(), ReportMode::CURRENT);

		$this->assertSame($expected, $values['nextcloud.background_job_mode']->value());
	}

	public static function backgroundJobModesProvider(): array {
		return [
			'cron' => ['cron', 'cron'],
			'webcron' => ['webcron', 'webcron'],
			'unexpected value' => ['https://cron.example.com/secret', 'other'],
		];
	}

	private function collector(): EnvironmentCollector {
		return new EnvironmentCollector(
			$this->userManager,
			self::getMockAppConfig(),
			$this->timeFactory,
			$this->runtime,
		);
	}

	private function period(): ReportPeriod {
		return ReportPeriod::monthContaining(new \DateTimeImmutable('2026-09-15T00:00:00Z'));
	}

	/**
	 * @param array<string, MetricValue> $values
	 * @return array<string, int|bool|string|null>
	 */
	private function values(array $values): array {
		return array_map(static fn (MetricValue $value) => $value->value(), $values);
	}
}
