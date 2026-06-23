<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\DefaultUserFolder;

use OCA\Libresign\Service\Policy\Model\ActorRole;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Provider\DefaultUserFolder\DefaultUserFolderPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DefaultUserFolderPolicyTest extends TestCase {
	public function testProviderBuildsDefinition(): void {
		$provider = new DefaultUserFolderPolicy();
		$this->assertSame([DefaultUserFolderPolicy::KEY], $provider->keys());

		$definition = $provider->get(DefaultUserFolderPolicy::KEY);
		$this->assertSame(DefaultUserFolderPolicy::KEY, $definition->key());
		$this->assertSame(DefaultUserFolderPolicy::DEFAULT_FOLDER, $definition->defaultSystemValue());
	}

	public function testNormalizesEmptyFolderToDefault(): void {
		$provider = new DefaultUserFolderPolicy();
		$definition = $provider->get(DefaultUserFolderPolicy::KEY);

		$this->assertSame('Team Certificates', $definition->normalizeValue('Team Certificates'));
		$this->assertSame(DefaultUserFolderPolicy::DEFAULT_FOLDER, $definition->normalizeValue(''));
	}

	public function testProviderSupportsDelegatedGroupAdminOverlays(): void {
		$provider = new DefaultUserFolderPolicy();
		$definition = $provider->get(DefaultUserFolderPolicy::KEY);

		$this->assertTrue($definition->supportsGroupAdminDelegation());
	}

	#[DataProvider('provideCanCurrentActorManageGroupPolicyCases')]
	public function testCanCurrentActorManageGroupPolicyWithDataProvider(
		ActorRole $actorRole,
		?PolicyLayer $systemPolicy,
		array $groupLayers,
		bool $expected,
	): void {
		$provider = new DefaultUserFolderPolicy();
		$definition = $provider->get(DefaultUserFolderPolicy::KEY);
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
		$provider = new DefaultUserFolderPolicy();
		$definition = $provider->get(DefaultUserFolderPolicy::KEY);
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
		yield 'system admin can always manage default user folder group policy' => [
			ActorRole::systemAdmin(),
			null,
			[],
			true,
		];

		yield 'regular user cannot manage default user folder group policy' => [
			ActorRole::regularUser(),
			null,
			[],
			false,
		];

		yield 'group admin with explicit global delegation can manage default user folder group policy' => [
			ActorRole::groupAdmin(1),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: 'Team Certificates'),
			[],
			true,
		];

		yield 'group admin with system-created default user folder seed can manage group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: true,
					visibleToChild: true,
					value: 'Team Certificates',
					createdBySystemAdmin: true,
				),
			],
			true,
		];

		yield 'group admin with delegated default user folder overlay lineage can manage group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: false,
					visibleToChild: true,
					value: 'Team Certificates',
					delegatedFromSystemCreatedSeed: true,
				),
			],
			true,
		];

		yield 'group admin without manageable groups cannot manage default user folder group policy' => [
			ActorRole::groupAdmin(0),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: 'Team Certificates'),
			[],
			false,
		];

		yield 'group admin without explicit or seed delegation cannot manage default user folder group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: false,
					visibleToChild: true,
					value: 'Team Certificates',
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
		yield 'system admin can edit any default user folder seed' => [
			ActorRole::systemAdmin(),
			null,
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: 'Team Certificates'),
			true,
		];

		yield 'group admin can edit visible default user folder seed' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: 'Team Certificates',
				createdBySystemAdmin: true,
			),
			true,
		];

		yield 'group admin can edit default user folder seed when explicit global delegation exists' => [
			ActorRole::groupAdmin(1),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: 'Team Certificates'),
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: 'Team Certificates'),
			true,
		];

		yield 'group admin cannot edit non-system-created default user folder seed without explicit global delegation' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: 'Team Certificates'),
			false,
		];

		yield 'group admin cannot edit invisible default user folder seed' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: false,
				value: 'Team Certificates',
				createdBySystemAdmin: true,
			),
			false,
		];

		yield 'group admin cannot edit default user folder seed that disallows child override' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: false,
				visibleToChild: true,
				value: 'Team Certificates',
				createdBySystemAdmin: true,
			),
			false,
		];

		yield 'regular user cannot edit default user folder seed' => [
			ActorRole::regularUser(),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: 'Team Certificates',
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
