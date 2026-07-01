<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\CrlValidation;

use OCA\Libresign\Service\Policy\Model\ActorRole;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Provider\CrlValidation\CrlValidationPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CrlValidationPolicyTest extends TestCase {
	public function testProviderBuildsCrlValidationDefinition(): void {
		$provider = new CrlValidationPolicy();
		$this->assertSame([CrlValidationPolicy::KEY], $provider->keys());

		$definition = $provider->get(CrlValidationPolicy::KEY);
		$this->assertSame(CrlValidationPolicy::KEY, $definition->key());
		$this->assertSame(['system', 'group'], $definition->supportedScopes());
		$this->assertTrue($definition->supportsScope('system'));
		$this->assertTrue($definition->supportsScope('group'));
		$this->assertFalse($definition->supportsScope('user'));
		$this->assertTrue($definition->normalizeValue('true'));
		$this->assertFalse($definition->normalizeValue('false'));
		$this->assertFalse($definition->normalizeValue(null));
		$this->assertFalse($definition->supportsUserPreference());
	}

	public function testProviderSupportsDelegatedGroupAdminOverlays(): void {
		$provider = new CrlValidationPolicy();
		$definition = $provider->get(CrlValidationPolicy::KEY);

		$this->assertTrue($definition->supportsGroupAdminDelegation());
	}

	#[DataProvider('provideCanCurrentActorManageGroupPolicyCases')]
	public function testCanCurrentActorManageGroupPolicyWithDataProvider(
		ActorRole $actorRole,
		?PolicyLayer $systemPolicy,
		array $groupLayers,
		bool $expected,
	): void {
		$provider = new CrlValidationPolicy();
		$definition = $provider->get(CrlValidationPolicy::KEY);
		$context = (new PolicyContext())->setActorRole($actorRole);

		$this->assertSame(
			$expected,
			$definition->canCurrentActorManageGroupPolicy($context, $systemPolicy, $groupLayers),
		);
	}

	#[DataProvider('provideCanCurrentActorEditSystemCreatedGroupPolicyCases')]
	public function testCanCurrentActorEditSystemCreatedGroupPolicyWithDataProvider(
		ActorRole $actorRole,
		?PolicyLayer $systemPolicy,
		PolicyLayer $existingPolicy,
		bool $expected,
	): void {
		$provider = new CrlValidationPolicy();
		$definition = $provider->get(CrlValidationPolicy::KEY);
		$context = (new PolicyContext())->setActorRole($actorRole);

		$this->assertSame(
			$expected,
			$definition->canCurrentActorEditSystemCreatedGroupPolicy($context, $systemPolicy, $existingPolicy),
		);
	}

	/**
	 * @return iterable<string, array{0: ActorRole, 1: ?PolicyLayer, 2: list<PolicyLayer>, 3: bool}>
	 */
	public static function provideCanCurrentActorManageGroupPolicyCases(): iterable {
		yield 'system admin can always manage CRL group policy' => [
			ActorRole::systemAdmin(),
			null,
			[],
			true,
		];

		yield 'regular user cannot manage CRL group policy' => [
			ActorRole::regularUser(),
			null,
			[],
			false,
		];

		yield 'group admin with explicit global delegation can manage CRL group policy' => [
			ActorRole::groupAdmin(1),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: true),
			[],
			true,
		];

		yield 'group admin with system-created CRL seed can manage group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: true,
					visibleToChild: true,
					value: true,
					createdBySystemAdmin: true,
				),
			],
			true,
		];

		yield 'group admin with delegated CRL overlay lineage can manage group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: false,
					visibleToChild: true,
					value: true,
					delegatedFromSystemCreatedSeed: true,
				),
			],
			true,
		];

		yield 'group admin without manageable groups cannot manage delegated CRL group policy' => [
			ActorRole::groupAdmin(0),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: true),
			[],
			false,
		];

		yield 'group admin without delegation cannot manage CRL group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: false,
					visibleToChild: true,
					value: true,
					createdBySystemAdmin: true,
				),
			],
			false,
		];
	}

	/**
	 * @return iterable<string, array{0: ActorRole, 1: ?PolicyLayer, 2: PolicyLayer, 3: bool}>
	 */
	public static function provideCanCurrentActorEditSystemCreatedGroupPolicyCases(): iterable {
		yield 'system admin can edit any CRL seed' => [
			ActorRole::systemAdmin(),
			null,
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: true),
			true,
		];

		yield 'group admin can edit visible CRL seed created by system admin' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: true,
				createdBySystemAdmin: true,
			),
			true,
		];

		yield 'group admin can edit CRL seed when explicit global delegation exists' => [
			ActorRole::groupAdmin(1),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: true),
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: true),
			true,
		];

		yield 'regular user cannot edit CRL seed' => [
			ActorRole::regularUser(),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: true,
				createdBySystemAdmin: true,
			),
			false,
		];

		yield 'group admin cannot edit CRL seed that is not visible to children' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: false,
				value: true,
				createdBySystemAdmin: true,
			),
			false,
		];

		yield 'group admin cannot edit CRL seed that forbids child override' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: false,
				visibleToChild: true,
				value: true,
				createdBySystemAdmin: true,
			),
			false,
		];

		yield 'group admin cannot edit CRL seed with null value' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: null,
				createdBySystemAdmin: true,
			),
			false,
		];

		yield 'group admin cannot edit non-system-created CRL seed without explicit global delegation' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: true),
			false,
		];
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
