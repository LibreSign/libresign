<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignatureText;

use OCA\Libresign\Service\Policy\Model\ActorRole;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicyValue;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class SignatureTextPolicyTest extends TestCase {
	private SignatureTextPolicy $policy;

	public function setUp(): void {
		parent::setUp();
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);
		$this->policy = new SignatureTextPolicy($l10n);
	}

	public function testKeysReturnsAllPolicyKeys(): void {
		$keys = $this->policy->keys();
		$this->assertCount(1, $keys);
		$this->assertContains(SignatureTextPolicy::KEY, $keys);
	}

	public function testConsolidatedPolicyExposesCanonicalDefaultInResolvedMeta(): void {
		$spec = $this->policy->get(SignatureTextPolicy::KEY);
		$resolvedStateMeta = $spec->resolvedStateMeta(new \OCA\Libresign\Service\Policy\Model\PolicyContext());

		$this->assertSame($spec->defaultSystemValue(), $resolvedStateMeta['defaultSystemValue']);
		$this->assertSame(SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY, $spec->getAppConfigKey());
		$this->assertSame(['system', 'group', 'user'], $spec->supportedScopes());
		$this->assertSame([], $spec->compositeChildren());
	}

	public function testConsolidatedPolicyPreservesDescriptionOnlyRenderMode(): void {
		$spec = $this->policy->get(SignatureTextPolicy::KEY);
		$normalized = $spec->normalizeValue([
			'template' => 'Signed with LibreSign',
			'render_mode' => 'description_only',
		]);

		$this->assertIsString($normalized);
		$this->assertSame('description_only', json_decode($normalized, true, flags: JSON_THROW_ON_ERROR)['render_mode']);
	}

	public function testSignatureTextPolicyValuePreservesDescriptionOnlyRenderMode(): void {
		$normalized = SignatureTextPolicyValue::normalize([
			'template' => 'Signed with LibreSign',
			'render_mode' => 'description_only',
		]);

		$this->assertSame('description_only', $normalized['render_mode']);
		$this->assertSame('description_only', json_decode(SignatureTextPolicyValue::encode($normalized), true, flags: JSON_THROW_ON_ERROR)['render_mode']);
	}

	public function testProviderSupportsDelegatedGroupAdminOverlays(): void {
		$definition = $this->policy->get(SignatureTextPolicy::KEY);

		$this->assertTrue($definition->supportsGroupAdminDelegation());
	}

	public function testConsolidatedPolicyNormalizesInvalidRenderModeToDefault(): void {
		$spec = $this->policy->get(SignatureTextPolicy::KEY);
		$normalized = $spec->normalizeValue([
			'template' => 'Signed with LibreSign',
			'render_mode' => 'invalid',
		]);

		$this->assertIsString($normalized);
		$this->assertSame('default', json_decode($normalized, true, flags: JSON_THROW_ON_ERROR)['render_mode']);
	}

	public function testGetWithInvalidKeyThrows(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unknown policy key: invalid_key');
		$this->policy->get('invalid_key');
	}

	#[DataProvider('provideCanCurrentActorManageGroupPolicyCases')]
	public function testCanCurrentActorManageGroupPolicyWithDataProvider(
		ActorRole $actorRole,
		?PolicyLayer $systemPolicy,
		array $groupLayers,
		bool $expected,
	): void {
		$definition = $this->policy->get(SignatureTextPolicy::KEY);
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
		$definition = $this->policy->get(SignatureTextPolicy::KEY);
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
		yield 'system admin can always manage signature stamp group policy' => [
			ActorRole::systemAdmin(),
			null,
			[],
			true,
		];

		yield 'regular user cannot manage signature stamp group policy' => [
			ActorRole::regularUser(),
			null,
			[],
			false,
		];

		yield 'group admin with explicit global delegation can manage signature stamp group policy' => [
			ActorRole::groupAdmin(1),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			[],
			true,
		];

		yield 'group admin with system-created signature stamp seed can manage group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: true,
					visibleToChild: true,
					value: 'seed',
					createdBySystemAdmin: true,
				),
			],
			true,
		];

		yield 'group admin with delegated signature stamp overlay lineage can manage group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: false,
					visibleToChild: true,
					value: 'seed',
					delegatedFromSystemCreatedSeed: true,
				),
			],
			true,
		];

		yield 'group admin without manageable groups cannot manage signature stamp group policy' => [
			ActorRole::groupAdmin(0),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			[],
			false,
		];

		yield 'group admin without explicit or seed delegation cannot manage signature stamp group policy' => [
			ActorRole::groupAdmin(1),
			null,
			[
				self::buildPolicyLayer(
					scope: 'group',
					allowChildOverride: false,
					visibleToChild: true,
					value: 'seed',
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
		yield 'system admin can edit any signature stamp seed' => [
			ActorRole::systemAdmin(),
			null,
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			true,
		];

		yield 'group admin can edit visible signature stamp seed' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: 'seed',
				createdBySystemAdmin: true,
			),
			true,
		];

		yield 'group admin can edit signature stamp seed when explicit global delegation exists' => [
			ActorRole::groupAdmin(1),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			true,
		];

		yield 'group admin cannot edit non-system-created signature stamp seed without explicit global delegation' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			false,
		];

		yield 'group admin cannot edit invisible signature stamp seed' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: false,
				value: 'seed',
				createdBySystemAdmin: true,
			),
			false,
		];

		yield 'group admin cannot edit signature stamp seed that disallows child override' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: false,
				visibleToChild: true,
				value: 'seed',
				createdBySystemAdmin: true,
			),
			false,
		];

		yield 'regular user cannot edit signature stamp seed' => [
			ActorRole::regularUser(),
			null,
			self::buildPolicyLayer(
				scope: 'group',
				allowChildOverride: true,
				visibleToChild: true,
				value: 'seed',
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
