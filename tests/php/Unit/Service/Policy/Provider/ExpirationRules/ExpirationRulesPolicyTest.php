<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\ExpirationRules;

use OCA\Libresign\Service\Policy\Model\ActorRole;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Provider\ExpirationRules\ExpirationRulesPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExpirationRulesPolicyTest extends TestCase {
	public function testProviderExposesAllExpirationKeys(): void {
		$provider = new ExpirationRulesPolicy();
		$this->assertSame([
			ExpirationRulesPolicy::KEY_MAXIMUM_VALIDITY,
			ExpirationRulesPolicy::KEY_RENEWAL_INTERVAL,
			ExpirationRulesPolicy::KEY_EXPIRY_IN_DAYS,
		], $provider->keys());
	}

	public function testNormalizesMaximumValidityToNonNegativeInteger(): void {
		$provider = new ExpirationRulesPolicy();
		$definition = $provider->get(ExpirationRulesPolicy::KEY_MAXIMUM_VALIDITY);

		$this->assertSame(600, $definition->normalizeValue('600'));
		$this->assertSame(0, $definition->normalizeValue(-10));
	}

	public function testNormalizesRenewalIntervalToNonNegativeInteger(): void {
		$provider = new ExpirationRulesPolicy();
		$definition = $provider->get(ExpirationRulesPolicy::KEY_RENEWAL_INTERVAL);

		$this->assertSame(1200, $definition->normalizeValue(1200));
		$this->assertSame(0, $definition->normalizeValue('-5'));
	}

	public function testNormalizesExpiryInDaysToPositiveInteger(): void {
		$provider = new ExpirationRulesPolicy();
		$definition = $provider->get(ExpirationRulesPolicy::KEY_EXPIRY_IN_DAYS);

		$this->assertSame(90, $definition->normalizeValue('90'));
		$this->assertSame(ExpirationRulesPolicy::DEFAULT_EXPIRY_IN_DAYS, $definition->normalizeValue(0));
	}

	public function testExpiryInDaysDoesNotSupportUserPreference(): void {
		$provider = new ExpirationRulesPolicy();
		$definition = $provider->get(ExpirationRulesPolicy::KEY_EXPIRY_IN_DAYS);

		$this->assertFalse($definition->supportsUserPreference(), 'expiry_in_days must not appear in user preferences');
	}

	#[DataProvider('provideDelegableExpirationKeys')]
	public function testDelegableExpirationKeysSupportDelegatedGroupAdminOverlaysAndExpectedPreferenceBehavior(
		string $policyKey,
		bool $supportsUserPreference,
	): void {
		$provider = new ExpirationRulesPolicy();
		$definition = $provider->get($policyKey);

		$this->assertTrue($definition->supportsGroupAdminDelegation());
		$this->assertSame($supportsUserPreference, $definition->supportsUserPreference());
	}

	#[DataProvider('provideDelegableExpirationKeys')]
	public function testGroupAdminCanManageDelegatedExpirationGroupPolicy(
		string $policyKey,
		bool $supportsUserPreference,
		int $delegatedValue,
	): void {
		$provider = new ExpirationRulesPolicy();
		$definition = $provider->get($policyKey);
		$context = (new PolicyContext())->setActorRole(ActorRole::groupAdmin(1));

		$canManage = $definition->canCurrentActorManageGroupPolicy(
			$context,
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: false,
					visibleToChild: true,
					value: $delegatedValue,
					delegatedFromSystemCreatedSeed: true,
				),
			],
		);

		$this->assertTrue($canManage);
	}

	#[DataProvider('provideDelegableExpirationKeys')]
	public function testGroupAdminCanEditSystemCreatedDelegableExpirationSeedWhenVisibleAndOverridable(
		string $policyKey,
		bool $supportsUserPreference,
		int $delegatedValue,
	): void {
		$provider = new ExpirationRulesPolicy();
		$definition = $provider->get($policyKey);
		$context = (new PolicyContext())->setActorRole(ActorRole::groupAdmin(1));

		$canEdit = $definition->canCurrentActorEditSystemCreatedGroupPolicy(
			$context,
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: $delegatedValue,
				createdBySystemAdmin: true,
			),
		);

		$this->assertTrue($canEdit);
	}

	/**
	 * @return iterable<string, array{0: string, 1: bool, 2: int}>
	 */
	public static function provideDelegableExpirationKeys(): iterable {
		yield 'maximum validity' => [ExpirationRulesPolicy::KEY_MAXIMUM_VALIDITY, false, 86400];
		yield 'renewal interval' => [ExpirationRulesPolicy::KEY_RENEWAL_INTERVAL, false, 3600];
		yield 'expiry in days' => [ExpirationRulesPolicy::KEY_EXPIRY_IN_DAYS, false, 365];
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
