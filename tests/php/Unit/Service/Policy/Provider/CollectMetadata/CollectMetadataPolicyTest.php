<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\CollectMetadata;

use OCA\Libresign\Service\Policy\Model\ActorRole;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Provider\CollectMetadata\CollectMetadataPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CollectMetadataPolicyTest extends TestCase {
	public function testProviderBuildsCollectMetadataDefinition(): void {
		$provider = new CollectMetadataPolicy();
		$this->assertSame([CollectMetadataPolicy::KEY], $provider->keys());
		$definition = $provider->get(CollectMetadataPolicy::KEY);

		$this->assertSame(CollectMetadataPolicy::KEY, $definition->key());
		$this->assertFalse($definition->defaultSystemValue());
		$this->assertSame([false, true], $definition->allowedValues(new PolicyContext()));
	}

	public function testProviderNormalizesCollectMetadataBooleanInputs(): void {
		$provider = new CollectMetadataPolicy();
		$definition = $provider->get(CollectMetadataPolicy::KEY);

		$this->assertTrue($definition->normalizeValue('1'));
		$this->assertTrue($definition->normalizeValue('true'));
		$this->assertFalse($definition->normalizeValue('0'));
		$this->assertFalse($definition->normalizeValue('false'));
		$this->assertFalse($definition->normalizeValue('unexpected-value'));
	}

	public function testProviderSupportsDelegatedGroupAdminOverlays(): void {
		$provider = new CollectMetadataPolicy();
		$definition = $provider->get(CollectMetadataPolicy::KEY);

		$this->assertTrue($definition->supportsGroupAdminDelegation());
	}

	#[DataProvider('provideCanCurrentActorManageGroupPolicyCases')]
	public function testCanCurrentActorManageGroupPolicyWithDataProvider(
		ActorRole $actorRole,
		?PolicyLayer $systemPolicy,
		array $groupLayers,
		bool $expected,
	): void {
		$provider = new CollectMetadataPolicy();
		$definition = $provider->get(CollectMetadataPolicy::KEY);
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
		$provider = new CollectMetadataPolicy();
		$definition = $provider->get(CollectMetadataPolicy::KEY);
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
		yield 'system admin can always manage collect metadata group policy' => [
			ActorRole::systemAdmin(),
			null,
			[],
			true,
		];

		yield 'regular user cannot manage collect metadata group policy' => [
			ActorRole::regularUser(),
			null,
			[],
			false,
		];

		yield 'group admin with explicit global delegation can manage collect metadata group policy' => [
			ActorRole::groupAdmin(1),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: true),
			[],
			true,
		];

		yield 'group admin with system-created collect metadata seed can manage group policy' => [
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

		yield 'group admin with delegated collect metadata overlay lineage can manage group policy' => [
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

		yield 'group admin without manageable groups cannot manage collect metadata group policy' => [
			ActorRole::groupAdmin(0),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: true),
			[],
			false,
		];

		yield 'group admin without explicit or seed delegation cannot manage collect metadata group policy' => [
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
		yield 'system admin can edit any collect metadata seed' => [
			ActorRole::systemAdmin(),
			null,
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: true),
			true,
		];

		yield 'group admin can edit visible collect metadata seed' => [
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

		yield 'group admin can edit collect metadata seed when explicit global delegation exists' => [
			ActorRole::groupAdmin(1),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: true),
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: true),
			true,
		];

		yield 'group admin cannot edit non-system-created collect metadata seed without explicit global delegation' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: true),
			false,
		];

		yield 'group admin cannot edit invisible collect metadata seed' => [
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

		yield 'group admin cannot edit collect metadata seed that disallows child override' => [
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

		yield 'regular user cannot edit collect metadata seed' => [
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
