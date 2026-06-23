<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\IdentificationDocuments;

use OCA\Libresign\Service\Policy\Model\ActorRole;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdentificationDocumentsPolicyTest extends TestCase {
	public function testProviderBuildsIdentificationDocumentsDefinition(): void {
		$provider = new IdentificationDocumentsPolicy();
		$this->assertSame([IdentificationDocumentsPolicy::KEY], $provider->keys());
		$definition = $provider->get(IdentificationDocumentsPolicy::KEY);

		$this->assertSame(IdentificationDocumentsPolicy::KEY, $definition->key());
		$this->assertSame([
			'enabled' => false,
			'approvers' => ['admin'],
		], $definition->defaultSystemValue());
		$this->assertSame([], $definition->allowedValues(new PolicyContext()));
	}

	public function testProviderSupportsDelegatedGroupAdminOverlays(): void {
		$provider = new IdentificationDocumentsPolicy();
		$definition = $provider->get(IdentificationDocumentsPolicy::KEY);

		$this->assertTrue($definition->supportsGroupAdminDelegation());
	}

	#[DataProvider('provideCanCurrentActorManageGroupPolicyCases')]
	public function testCanCurrentActorManageGroupPolicyWithDataProvider(
		ActorRole $actorRole,
		?PolicyLayer $systemPolicy,
		array $groupLayers,
		bool $expected,
	): void {
		$provider = new IdentificationDocumentsPolicy();
		$definition = $provider->get(IdentificationDocumentsPolicy::KEY);
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
		$provider = new IdentificationDocumentsPolicy();
		$definition = $provider->get(IdentificationDocumentsPolicy::KEY);
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
		yield 'system admin can always manage identification documents group policy' => [
			ActorRole::systemAdmin(),
			null,
			[],
			true,
		];

		yield 'regular user cannot manage identification documents group policy' => [
			ActorRole::regularUser(),
			null,
			[],
			false,
		];

		yield 'group admin with explicit global delegation can manage identification documents group policy' => [
			ActorRole::groupAdmin(1),
			self::buildPolicyLayer(
				scope: 'global',
				allowChildOverride: true,
				visibleToChild: true,
				value: ['enabled' => true, 'approvers' => ['admin']],
			),
			[],
			true,
		];

		yield 'group admin with system-created identification documents seed can manage group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: true,
					visibleToChild: true,
					value: ['enabled' => true, 'approvers' => ['finance']],
					createdBySystemAdmin: true,
				),
			],
			true,
		];

		yield 'group admin with delegated identification documents overlay lineage can manage group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: false,
					visibleToChild: true,
					value: ['enabled' => true, 'approvers' => ['finance']],
					delegatedFromSystemCreatedSeed: true,
				),
			],
			true,
		];

		yield 'group admin without manageable groups cannot manage identification documents group policy' => [
			ActorRole::groupAdmin(0),
			self::buildPolicyLayer(
				scope: 'global',
				allowChildOverride: true,
				visibleToChild: true,
				value: ['enabled' => true, 'approvers' => ['admin']],
			),
			[],
			false,
		];

		yield 'group admin without explicit or seed delegation cannot manage identification documents group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: false,
					visibleToChild: true,
					value: ['enabled' => true, 'approvers' => ['finance']],
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
		yield 'system admin can edit any identification documents seed' => [
			ActorRole::systemAdmin(),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: ['enabled' => true, 'approvers' => ['finance']],
			),
			true,
		];

		yield 'group admin can edit visible identification documents seed' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: ['enabled' => true, 'approvers' => ['finance']],
				createdBySystemAdmin: true,
			),
			true,
		];

		yield 'group admin can edit identification documents seed when explicit global delegation exists' => [
			ActorRole::groupAdmin(1),
			self::buildPolicyLayer(
				scope: 'global',
				allowChildOverride: true,
				visibleToChild: true,
				value: ['enabled' => true, 'approvers' => ['admin']],
			),
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: ['enabled' => true, 'approvers' => ['finance']],
			),
			true,
		];

		yield 'group admin cannot edit non-system-created identification documents seed without explicit global delegation' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: ['enabled' => true, 'approvers' => ['finance']],
			),
			false,
		];

		yield 'group admin cannot edit invisible identification documents seed' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: false,
				value: ['enabled' => true, 'approvers' => ['finance']],
				createdBySystemAdmin: true,
			),
			false,
		];

		yield 'group admin cannot edit identification documents seed that disallows child override' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: false,
				visibleToChild: true,
				value: ['enabled' => true, 'approvers' => ['finance']],
				createdBySystemAdmin: true,
			),
			false,
		];

		yield 'regular user cannot edit identification documents seed' => [
			ActorRole::regularUser(),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: ['enabled' => true, 'approvers' => ['finance']],
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
