<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\IdentifyMethods;

use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Model\ActorRole;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class IdentifyMethodsPolicyTest extends TestCase {
	private IdentifyMethodService&MockObject $identifyMethodService;

	public function setUp(): void {
		parent::setUp();
		$this->identifyMethodService = $this->createMock(IdentifyMethodService::class);
	}

	public function testProviderBuildsIdentifyMethodsDefinition(): void {
		$provider = new IdentifyMethodsPolicy($this->identifyMethodService);
		$this->assertSame([IdentifyMethodsPolicy::KEY], $provider->keys());

		$definition = $provider->get(IdentifyMethodsPolicy::KEY);
		$this->assertSame(IdentifyMethodsPolicy::KEY, $definition->key());
		$this->assertSame([], $definition->defaultSystemValue());
	}

	public function testProviderNormalizesIdentifyMethodsPayload(): void {
		$provider = new IdentifyMethodsPolicy($this->identifyMethodService);
		$definition = $provider->get(IdentifyMethodsPolicy::KEY);

		$normalized = $definition->normalizeValue([
			[
				'name' => 'email',
				'friendly_name' => 'Email',
				'enabled' => 1,
				'can_create_account' => '0',
				'signatureMethods' => [
					'email' => [
						'enabled' => true,
						'label' => 'Email token',
					],
					'clickToSign' => [
						'enabled' => false,
					],
				],
			],
		]);

		$this->assertSame([
			'factors' => [
				[
					'name' => 'email',
					'enabled' => true,
					'signatureMethods' => [
						'email' => [
							'enabled' => true,
							'label' => 'Email token',
							'name' => 'Email token',
						],
						'clickToSign' => [
							'enabled' => false,
						],
					],
					'friendly_name' => 'Email',
				],
			],
			'can_create_account' => false,
		], $normalized);
	}

	public function testProviderUsesIdentifyMethodsCatalogWhenPayloadIsEmpty(): void {
		$this->identifyMethodService
			->method('getFriendlyNamesMap')
			->willReturn([
				'account' => 'Account',
				'email' => 'Email',
			]);

		$provider = new IdentifyMethodsPolicy($this->identifyMethodService);
		$definition = $provider->get(IdentifyMethodsPolicy::KEY);

		$normalized = $definition->normalizeValue([]);

		$this->assertSame('account', $normalized['factors'][0]['name']);
		$this->assertSame('email', $normalized['factors'][1]['name']);
		$this->assertSame(true, $normalized['factors'][0]['enabled']);
	}

	public function testProviderSupportsDelegatedGroupAdminOverlays(): void {
		$provider = new IdentifyMethodsPolicy($this->identifyMethodService);
		$definition = $provider->get(IdentifyMethodsPolicy::KEY);

		$this->assertTrue($definition->supportsGroupAdminDelegation());
	}

	#[DataProvider('provideDelegatedIdentifyMethodsValidationCases')]
	public function testValidateDelegatedIdentifyMethodsOverrideWithDataProvider(
		array $proposedValue,
		array $parentValue,
		?string $expectedExceptionMessage,
	): void {
		$provider = new IdentifyMethodsPolicy($this->identifyMethodService);
		$definition = $provider->get(IdentifyMethodsPolicy::KEY);

		if ($expectedExceptionMessage !== null) {
			$this->expectException(\InvalidArgumentException::class);
			$this->expectExceptionMessage($expectedExceptionMessage);
		}

		$definition->validateGroupAdminDelegatedValue(
			$proposedValue,
			$parentValue,
			new PolicyContext(),
		);

		if ($expectedExceptionMessage === null) {
			$this->addToAssertionCount(1);
		}
	}

	#[DataProvider('provideCanCurrentActorManageGroupPolicyCases')]
	public function testCanCurrentActorManageGroupPolicyWithDataProvider(
		ActorRole $actorRole,
		?PolicyLayer $systemPolicy,
		array $groupLayers,
		bool $expected,
	): void {
		$provider = new IdentifyMethodsPolicy($this->identifyMethodService);
		$definition = $provider->get(IdentifyMethodsPolicy::KEY);
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
		$provider = new IdentifyMethodsPolicy($this->identifyMethodService);
		$definition = $provider->get(IdentifyMethodsPolicy::KEY);
		$context = (new PolicyContext())->setActorRole($actorRole);

		$this->assertSame(
			$expected,
			$definition->canCurrentActorEditSystemCreatedGroupPolicy($context, $systemPolicy, $existingPolicy),
		);
	}

	/**
	 * @return iterable<string, array{0: array<string, list<array{name: string, enabled: bool, signatureMethods: array<string, mixed> }>>, 1: array<string, list<array{name: string, enabled: bool, signatureMethods: array<string, mixed> }>>, 2: ?string}>
	 */
	public static function provideDelegatedIdentifyMethodsValidationCases(): iterable {
		$exceptionMessage = 'Delegated identify methods overrides can only enable factors already granted by the system administrator.';

		yield 'allows disabling a delegated factor' => [
			self::buildPolicyValue([
				'account' => true,
				'email' => false,
			]),
			self::buildPolicyValue([
				'account' => true,
				'email' => true,
			]),
			null,
		];

		yield 'allows keeping exact granted subset' => [
			self::buildPolicyValue([
				'account' => true,
			]),
			self::buildPolicyValue([
				'account' => true,
			]),
			null,
		];

		yield 'ignores disabled extra factors absent from parent grant' => [
			self::buildPolicyValue([
				'account' => true,
				'sms' => false,
			]),
			self::buildPolicyValue([
				'account' => true,
			]),
			null,
		];

		yield 'rejects re-enabling factor disabled in parent' => [
			self::buildPolicyValue([
				'account' => true,
				'email' => true,
			]),
			self::buildPolicyValue([
				'account' => true,
				'email' => false,
			]),
			$exceptionMessage,
		];

		yield 'rejects enabling factor absent from parent grant' => [
			self::buildPolicyValue([
				'account' => true,
				'sms' => true,
			]),
			self::buildPolicyValue([
				'account' => true,
			]),
			$exceptionMessage,
		];
	}

	/**
	 * @return iterable<string, array{0: ActorRole, 1: ?PolicyLayer, 2: list<PolicyLayer>, 3: bool}>
	 */
	public static function provideCanCurrentActorManageGroupPolicyCases(): iterable {
		yield 'system admin can always manage group policy' => [
			ActorRole::systemAdmin(),
			null,
			[],
			true,
		];

		yield 'regular user cannot manage group policy' => [
			ActorRole::regularUser(),
			null,
			[],
			false,
		];

		yield 'group admin with explicit global delegation can manage group policy' => [
			ActorRole::groupAdmin(1),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			[],
			true,
		];

		yield 'group admin with system-created seed can manage group policy' => [
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

		yield 'group admin with delegated overlay lineage can manage group policy' => [
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

		yield 'group admin without manageable groups cannot manage group policy' => [
			ActorRole::groupAdmin(0),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			[],
			false,
		];

		yield 'group admin without explicit or seed delegation cannot manage group policy' => [
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
		yield 'system admin can edit any system-created group policy' => [
			ActorRole::systemAdmin(),
			null,
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			true,
		];

		yield 'group admin can edit visible system-created seed' => [
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

		yield 'group admin can edit matching seed when explicit global delegation exists' => [
			ActorRole::groupAdmin(1),
			self::buildPolicyLayer(scope: 'global', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			true,
		];

		yield 'group admin cannot edit non-system-created seed without explicit global delegation' => [
			ActorRole::groupAdmin(1),
			null,
			self::buildPolicyLayer(scope: 'group', allowChildOverride: true, visibleToChild: true, value: 'seed'),
			false,
		];

		yield 'group admin cannot edit invisible seed' => [
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

		yield 'group admin cannot edit seed that disallows child override' => [
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

		yield 'regular user cannot edit system-created group policy' => [
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

	/** @param array<string, bool> $enabledFactors */
	private static function buildPolicyValue(array $enabledFactors): array {
		$factors = [];
		foreach ($enabledFactors as $name => $enabled) {
			$factors[] = [
				'name' => $name,
				'enabled' => $enabled,
				'signatureMethods' => [],
			];
		}

		return ['factors' => $factors];
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
