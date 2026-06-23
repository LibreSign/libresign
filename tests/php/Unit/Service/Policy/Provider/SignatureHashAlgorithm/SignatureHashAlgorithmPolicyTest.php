<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignatureHashAlgorithm;

use OCA\Libresign\Service\Policy\Model\ActorRole;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Provider\SignatureHashAlgorithm\SignatureHashAlgorithmPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SignatureHashAlgorithmPolicyTest extends TestCase {
	public function testProviderBuildsDefinition(): void {
		$provider = new SignatureHashAlgorithmPolicy();
		$this->assertSame([SignatureHashAlgorithmPolicy::KEY], $provider->keys());

		$definition = $provider->get(SignatureHashAlgorithmPolicy::KEY);
		$this->assertSame(SignatureHashAlgorithmPolicy::KEY, $definition->key());
		$this->assertSame('SHA256', $definition->defaultSystemValue());
	}

	public function testNormalizesToAllowedAlgorithm(): void {
		$provider = new SignatureHashAlgorithmPolicy();
		$definition = $provider->get(SignatureHashAlgorithmPolicy::KEY);

		$this->assertSame('SHA512', $definition->normalizeValue('sha512'));
		$this->assertSame('SHA256', $definition->normalizeValue('unknown'));
	}

	public function testProviderSupportsDelegatedGroupAdminOverlays(): void {
		$provider = new SignatureHashAlgorithmPolicy();
		$definition = $provider->get(SignatureHashAlgorithmPolicy::KEY);

		$this->assertTrue($definition->supportsGroupAdminDelegation());
	}

	#[DataProvider('provideCanCurrentActorManageGroupPolicyCases')]
	public function testCanCurrentActorManageGroupPolicyWithDataProvider(
		ActorRole $actorRole,
		?PolicyLayer $systemPolicy,
		array $groupLayers,
		bool $expected,
	): void {
		$provider = new SignatureHashAlgorithmPolicy();
		$definition = $provider->get(SignatureHashAlgorithmPolicy::KEY);
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
		$provider = new SignatureHashAlgorithmPolicy();
		$definition = $provider->get(SignatureHashAlgorithmPolicy::KEY);
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
		yield 'system admin can always manage signature hash algorithm group policy' => [
			ActorRole::systemAdmin(),
			null,
			[],
			true,
		];

		yield 'regular user cannot manage signature hash algorithm group policy' => [
			ActorRole::regularUser(),
			null,
			[],
			false,
		];

		yield 'group admin with explicit global delegation can manage signature hash algorithm group policy' => [
			ActorRole::groupAdmin(1),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: 'SHA512'),
			[],
			true,
		];

		yield 'group admin with system-created signature hash algorithm seed can manage group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: true,
					visibleToChild: true,
					value: 'SHA512',
					createdBySystemAdmin: true,
				),
			],
			true,
		];

		yield 'group admin with delegated signature hash algorithm overlay lineage can manage group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: false,
					visibleToChild: true,
					value: 'SHA512',
					delegatedFromSystemCreatedSeed: true,
				),
			],
			true,
		];

		yield 'group admin without manageable groups cannot manage signature hash algorithm group policy' => [
			ActorRole::groupAdmin(0),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: 'SHA512'),
			[],
			false,
		];

		yield 'group admin without explicit or seed delegation cannot manage signature hash algorithm group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: false,
					visibleToChild: true,
					value: 'SHA512',
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
		yield 'system admin can edit any signature hash algorithm seed' => [
			ActorRole::systemAdmin(),
			null,
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: 'SHA512'),
			true,
		];

		yield 'group admin can edit visible signature hash algorithm seed' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: 'SHA512',
				createdBySystemAdmin: true,
			),
			true,
		];

		yield 'group admin can edit signature hash algorithm seed when explicit global delegation exists' => [
			ActorRole::groupAdmin(1),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: 'SHA512'),
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: 'SHA512'),
			true,
		];

		yield 'group admin cannot edit non-system-created signature hash algorithm seed without explicit global delegation' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: 'SHA512'),
			false,
		];

		yield 'group admin cannot edit invisible signature hash algorithm seed' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: false,
				value: 'SHA512',
				createdBySystemAdmin: true,
			),
			false,
		];

		yield 'group admin cannot edit signature hash algorithm seed that disallows child override' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: false,
				visibleToChild: true,
				value: 'SHA512',
				createdBySystemAdmin: true,
			),
			false,
		];

		yield 'regular user cannot edit signature hash algorithm seed' => [
			ActorRole::regularUser(),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: 'SHA512',
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
