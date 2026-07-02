<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Reminder;

use OCA\Libresign\Service\Policy\Model\ActorRole;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Provider\Reminder\ReminderPolicy;
use OCA\Libresign\Service\Policy\Provider\Reminder\ReminderPolicyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReminderPolicyTest extends TestCase {
	public function testProviderBuildsReminderDefinition(): void {
		$provider = new ReminderPolicy();
		$this->assertSame([ReminderPolicy::KEY], $provider->keys());

		$definition = $provider->get(ReminderPolicy::KEY);
		$this->assertSame(ReminderPolicy::KEY, $definition->key());
		$this->assertSame(ReminderPolicy::SYSTEM_APP_CONFIG_KEY, $definition->getAppConfigKey());
		$this->assertSame(
			ReminderPolicyValue::encode(ReminderPolicyValue::defaults()),
			$definition->defaultSystemValue(),
		);
		$this->assertSame(['system', 'group', 'user'], $definition->supportedScopes());
	}

	#[DataProvider('providerNormalizeReminderPayload')]
	public function testProviderNormalizesReminderPayload(array $input, string $expected): void {
		$provider = new ReminderPolicy();
		$definition = $provider->get(ReminderPolicy::KEY);

		$normalized = $definition->normalizeValue($input);

		$this->assertSame($expected, $normalized);
	}

	public static function providerNormalizeReminderPayload(): array {
		return [
			'valid reminder payload' => [
				[
					'days_before' => '2',
					'days_between' => 3,
					'max' => '4',
					'send_timer' => '09:45',
				],
				'{"days_before":2,"days_between":3,"max":4,"send_timer":"09:45"}',
			],
			'invalid reminder payload collapses to disabled state' => [
				[
					'days_before' => -5,
					'days_between' => 'not-number',
					'max' => -1,
					'send_timer' => 'invalid',
				],
				'{"days_before":0,"days_between":0,"max":0,"send_timer":""}',
			],
			'enabled reminder payload without send time falls back to default time' => [
				[
					'days_before' => 2,
					'days_between' => 3,
					'max' => 4,
					'send_timer' => '',
				],
				'{"days_before":2,"days_between":3,"max":4,"send_timer":"10:00"}',
			],
		];
	}

	public function testProviderSupportsDelegatedGroupAdminOverlays(): void {
		$provider = new ReminderPolicy();
		$definition = $provider->get(ReminderPolicy::KEY);

		$this->assertTrue($definition->supportsGroupAdminDelegation());
	}

	public function testGroupAdminCanManageReminderGroupPolicyWhenDelegatedFromSystemCreatedSeed(): void {
		$provider = new ReminderPolicy();
		$definition = $provider->get(ReminderPolicy::KEY);
		$context = (new PolicyContext())->setActorRole(ActorRole::groupAdmin(1));

		$canManage = $definition->canCurrentActorManageGroupPolicy(
			$context,
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: false,
					visibleToChild: true,
					value: ReminderPolicyValue::encode(ReminderPolicyValue::defaults()),
					delegatedFromSystemCreatedSeed: true,
				),
			],
		);

		$this->assertTrue($canManage);
	}

	public function testGroupAdminCanEditSystemCreatedReminderSeedWhenVisibleAndOverridable(): void {
		$provider = new ReminderPolicy();
		$definition = $provider->get(ReminderPolicy::KEY);
		$context = (new PolicyContext())->setActorRole(ActorRole::groupAdmin(1));

		$canEdit = $definition->canCurrentActorEditSystemCreatedGroupPolicy(
			$context,
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: ReminderPolicyValue::encode(ReminderPolicyValue::defaults()),
				createdBySystemAdmin: true,
			),
		);

		$this->assertTrue($canEdit);
	}

	private static function buildPolicyLayer(
		string $scope,
		bool $allowChildOverride,
		bool $visibleToChild,
		mixed $value,
		bool $createdBySystemAdmin = false,
		bool $delegatedFromSystemCreatedSeed = false,
	): PolicyLayer {
		return (new PolicyLayer())
			->setScope($scope)
			->setAllowChildOverride($allowChildOverride)
			->setVisibleToChild($visibleToChild)
			->setValue($value)
			->setCreatedBySystemAdmin($createdBySystemAdmin)
			->setDelegatedFromSystemCreatedSeed($delegatedFromSystemCreatedSeed);
	}
}
