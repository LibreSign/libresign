<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Service\ActivitySettingsStore;
use OCA\Libresign\Service\NotificationPreferenceResolver;
use OCP\Config\IUserConfig;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NotificationPreferenceResolverTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private IUserConfig&MockObject $userConfig;
	private ActivitySettingsStore&MockObject $activitySettingsStore;
	private NotificationPreferenceResolver $resolver;

	protected function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->userConfig = $this->createMock(IUserConfig::class);
		$this->activitySettingsStore = $this->createMock(ActivitySettingsStore::class);

		$this->resolver = new NotificationPreferenceResolver(
			$this->appConfig,
			$this->userConfig,
			$this->activitySettingsStore,
		);
	}

	public static function provideEmailFallbackScenarios(): array {
		return [
			'app config disabled with numeric zero' => ['0', '1', true],
			'app config disabled with boolean-like string' => ['false', '1', true],
			'user config disabled' => ['1', '0', true],
			'both settings enabled' => ['1', '1', false],
		];
	}

	#[DataProvider('provideEmailFallbackScenarios')]
	public function testEmailFallbackUsesMockedConfigValues(
		string $appSetting,
		string $userSetting,
		bool $expectedDisabled,
	): void {
		$this->activitySettingsStore->expects($this->once())
			->method('isAvailable')
			->willReturn(false);

		$this->appConfig->expects($this->once())
			->method('getValueString')
			->with('activity', 'notify_email_libresign_file_signed', '1')
			->willReturn($appSetting);

		$this->userConfig->expects($appSetting === '1' ? $this->once() : $this->never())
			->method('getValueString')
			->with('admin', 'activity', 'notify_email_libresign_file_signed', '1')
			->willReturn($userSetting);

		$this->assertSame(
			$expectedDisabled,
			$this->resolver->isEmailNotificationDisabled('admin', SignedEvent::FILE_SIGNED, true),
		);
	}

	public function testInAppFallbackUsesNotificationChannel(): void {
		$this->activitySettingsStore->expects($this->once())
			->method('isAvailable')
			->willReturn(false);

		$this->appConfig->expects($this->once())
			->method('getValueString')
			->with('activity', 'notify_notification_libresign_file_signed', '1')
			->willReturn('1');

		$this->userConfig->expects($this->once())
			->method('getValueString')
			->with('admin', 'activity', 'notify_notification_libresign_file_signed', '1')
			->willReturn('0');

		$this->assertTrue($this->resolver->isInAppNotificationDisabled('admin', SignedEvent::FILE_SIGNED, true));
	}

	public function testUsesActivitySettingsWhenAvailable(): void {
		$this->activitySettingsStore->expects($this->once())
			->method('isAvailable')
			->willReturn(true);
		$this->activitySettingsStore->expects($this->once())
			->method('hasSetting')
			->with(SignedEvent::FILE_SIGNED)
			->willReturn(true);
		$this->activitySettingsStore->expects($this->once())
			->method('getAdminSetting')
			->with('email', SignedEvent::FILE_SIGNED)
			->willReturn('1');
		$this->activitySettingsStore->expects($this->once())
			->method('getUserSetting')
			->with('admin', 'email', SignedEvent::FILE_SIGNED)
			->willReturn('0');

		$this->appConfig->expects($this->never())->method('getValueString');
		$this->userConfig->expects($this->never())->method('getValueString');

		$this->assertTrue($this->resolver->isEmailNotificationDisabled('admin', SignedEvent::FILE_SIGNED, true));
	}

	public function testUnknownActivitySettingDoesNotDisableNotificationsWhenRegistryCheckIsRequired(): void {
		$this->activitySettingsStore->expects($this->once())
			->method('isAvailable')
			->willReturn(true);
		$this->activitySettingsStore->expects($this->once())
			->method('hasSetting')
			->with(SignedEvent::FILE_SIGNED)
			->willReturn(false);

		$this->activitySettingsStore->expects($this->never())->method('getAdminSetting');
		$this->activitySettingsStore->expects($this->never())->method('getUserSetting');

		$this->assertFalse($this->resolver->isEmailNotificationDisabled('admin', SignedEvent::FILE_SIGNED, true));
	}

	public function testCanSkipActivityRegistryCheckForResultEnricherScenario(): void {
		$this->activitySettingsStore->expects($this->once())
			->method('isAvailable')
			->willReturn(true);
		$this->activitySettingsStore->expects($this->never())->method('hasSetting');
		$this->activitySettingsStore->expects($this->once())
			->method('getAdminSetting')
			->with('email', 'libresign_file_to_sign')
			->willReturn('1');
		$this->activitySettingsStore->expects($this->once())
			->method('getUserSetting')
			->with('john', 'email', 'libresign_file_to_sign')
			->willReturn('1');

		$this->assertFalse($this->resolver->isEmailNotificationDisabled('john', 'libresign_file_to_sign', false));
	}
}
