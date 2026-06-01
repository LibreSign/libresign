<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Runtime;

require_once __DIR__ . '/../../../../../../lib/Service/Policy/Contract/IPolicySource.php';

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicySource;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicyValue;
use OCA\Libresign\Service\Policy\Runtime\DefaultPolicyResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DefaultPolicyResolverTest extends TestCase {
	public function testResolveUsesDefinitionDefaultWhenNoLayersExist(): void {
		$resolver = new DefaultPolicyResolver(new InMemoryPolicySource());

		$resolved = $resolver->resolve($this->getDefinition(), new PolicyContext());

		$this->assertSame('none', $resolved->getEffectiveValue());
		$this->assertSame('system', $resolved->getSourceScope());
		$this->assertSame(['none', 'parallel', 'ordered_numeric'], $resolved->getAllowedValues());
		$this->assertFalse($resolved->isEditableByCurrentActor());
	}

	public function testResolveAppliesGroupValueWhenSystemAllowsOverride(): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('system')
			->setValue('none')
			->setAllowChildOverride(true)
			->setVisibleToChild(true);
		$source->groupLayers = [
			(new PolicyLayer())
				->setScope('group')
				->setValue('ordered_numeric')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues(['parallel', 'ordered_numeric']),
		];

		$resolver = new DefaultPolicyResolver($source);

		$resolved = $resolver->resolve($this->getDefinition(), PolicyContext::fromUserId('john'));

		$this->assertSame('ordered_numeric', $resolved->getEffectiveValue());
		$this->assertSame('group', $resolved->getSourceScope());
		$this->assertFalse($resolved->isEditableByCurrentActor());
		$this->assertTrue($resolved->canSaveAsUserDefault());
		$this->assertTrue($resolved->canUseAsRequestOverride());
	}

	public function testResolveClearsInvalidUserPreferenceWhenGroupBlocksOverride(): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('system')
			->setValue('none')
			->setAllowChildOverride(true)
			->setVisibleToChild(true);
		$source->groupLayers = [
			(new PolicyLayer())
				->setScope('group')
				->setValue('parallel')
				->setAllowChildOverride(false)
				->setVisibleToChild(true)
				->setAllowedValues(['parallel']),
		];
		$source->userPreference = (new PolicyLayer())
			->setScope('user')
			->setValue('ordered_numeric');

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve($this->getDefinition(), PolicyContext::fromUserId('john'));

		$this->assertSame('parallel', $resolved->getEffectiveValue());
		$this->assertSame('group', $resolved->getSourceScope());
		$this->assertTrue($resolved->wasPreferenceCleared());
		$this->assertFalse($resolved->canSaveAsUserDefault());
		$this->assertFalse($resolved->canUseAsRequestOverride());
		$this->assertSame('group', $resolved->getBlockedBy());
		$this->assertTrue($source->userPreferenceCleared);
	}

	public function testResolveAppliesRequestOverrideWhenAllowed(): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('global')
			->setValue('none')
			->setAllowChildOverride(true)
			->setVisibleToChild(true);
		$source->groupLayers = [
			(new PolicyLayer())
				->setScope('group')
				->setValue('parallel')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues(['parallel', 'ordered_numeric']),
		];
		$source->requestOverride = (new PolicyLayer())
			->setScope('request')
			->setValue('ordered_numeric');

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve($this->getDefinition(), PolicyContext::fromUserId('john'));

		$this->assertSame('ordered_numeric', $resolved->getEffectiveValue());
		$this->assertSame('request', $resolved->getSourceScope());
		$this->assertTrue($resolved->canUseAsRequestOverride());
		$this->assertNull($resolved->getBlockedBy());
	}

	public function testResolveValueChoiceUnionsConflictingGroupValues(): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('system')
			->setValue('none')
			->setAllowChildOverride(true)
			->setVisibleToChild(true);
		$source->groupLayers = [
			(new PolicyLayer())
				->setScope('group')
				->setValue('ordered_numeric')
				->setAllowChildOverride(false)
				->setVisibleToChild(true)
				->setAllowedValues(['ordered_numeric']),
			(new PolicyLayer())
				->setScope('group')
				->setValue('parallel')
				->setAllowChildOverride(false)
				->setVisibleToChild(true)
				->setAllowedValues(['parallel']),
		];

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve($this->getValueChoiceDefinition(), PolicyContext::fromUserId('john'));

		$this->assertSame('parallel', $resolved->getEffectiveValue());
		$this->assertSame('group', $resolved->getSourceScope());
		$this->assertSame(['parallel', 'ordered_numeric'], $resolved->getAllowedValues());
		$this->assertFalse($resolved->isEditableByCurrentActor());
		$this->assertTrue($resolved->canSaveAsUserDefault());
		$this->assertTrue($resolved->canUseAsRequestOverride());
	}

	public function testResolveValueChoiceLetsCustomizableGroupBroadenFixedGroupChoice(): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('system')
			->setValue('none')
			->setAllowChildOverride(true)
			->setVisibleToChild(true);
		$source->groupLayers = [
			(new PolicyLayer())
				->setScope('group')
				->setValue('ordered_numeric')
				->setAllowChildOverride(false)
				->setVisibleToChild(true)
				->setAllowedValues(['ordered_numeric']),
			(new PolicyLayer())
				->setScope('group')
				->setValue('ordered_numeric')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues([]),
		];

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve($this->getValueChoiceDefinition(), PolicyContext::fromUserId('john'));

		$this->assertSame('ordered_numeric', $resolved->getEffectiveValue());
		$this->assertSame(['parallel', 'ordered_numeric'], $resolved->getAllowedValues());
		$this->assertTrue($resolved->canUseAsRequestOverride());
	}

	public function testResolveDoesNotApplyGroupValueWhenSystemBlocksOverride(): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('system')
			->setValue('ordered_numeric')
			->setAllowChildOverride(false)
			->setVisibleToChild(true)
			->setAllowedValues(['ordered_numeric']);
		$source->groupLayers = [
			(new PolicyLayer())
				->setScope('group')
				->setValue('parallel')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues(['parallel', 'ordered_numeric']),
		];

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve($this->getDefinition(), PolicyContext::fromUserId('john'));

		$this->assertSame('ordered_numeric', $resolved->getEffectiveValue());
		$this->assertSame('system', $resolved->getSourceScope());
		$this->assertSame(['ordered_numeric'], $resolved->getAllowedValues());
		$this->assertFalse($resolved->isEditableByCurrentActor());
		$this->assertFalse($resolved->canUseAsRequestOverride());
	}

	public function testResolveKeepsPolicyEditableForGroupAdminEvenWhenUserLayerBlocksFurtherOverrides(): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('global')
			->setValue('none')
			->setAllowChildOverride(true)
			->setVisibleToChild(true);
		$source->groupLayers = [
			(new PolicyLayer())
				->setScope('group')
				->setValue('parallel')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues(['parallel', 'ordered_numeric']),
		];
		$source->userPolicy = (new PolicyLayer())
			->setScope('user_policy')
			->setValue('ordered_numeric')
			->setAllowChildOverride(false)
			->setVisibleToChild(true)
			->setAllowedValues(['ordered_numeric']);

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve(
			$this->getDefinition(),
			PolicyContext::fromUserId('ceo')->setActorCapabilities([
				'canManageSystemPolicies' => false,
				'canManageGroupPolicies' => true,
			]),
		);

		$this->assertTrue($resolved->isEditableByCurrentActor());
	}

	public function testResolveDoesNotKeepPolicyEditableForGroupAdminOnlyBecauseGroupRuleExists(): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('system')
			->setValue('none')
			->setAllowChildOverride(true)
			->setVisibleToChild(true);
		$source->groupLayers = [
			(new PolicyLayer())
				->setScope('group')
				->setValue('parallel')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues(['parallel', 'ordered_numeric']),
		];

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve(
			$this->getDefinition(),
			PolicyContext::fromUserId('ceo')->setActorCapabilities([
				'canManageSystemPolicies' => false,
				'canManageGroupPolicies' => true,
			]),
		);

		$this->assertFalse($resolved->isEditableByCurrentActor());
	}

	#[DataProvider('provideRequestSignGroupsEditableByManageableGroupCountCases')]
	public function testResolveRequestSignGroupsEditableFlagFollowsManageableGroupThreshold(
		int $manageableGroupCount,
		bool $expectedEditable,
	): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('global')
			->setValue(RequestSignGroupsPolicyValue::encode(['admin']))
			->setAllowChildOverride(true)
			->setVisibleToChild(true);

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve(
			$this->getRequestSignGroupsDefinition(),
			PolicyContext::fromUserId('ceo')->setActorCapabilities([
				'canManageSystemPolicies' => false,
				'canManageGroupPolicies' => true,
				'manageableGroupCount' => $manageableGroupCount,
			]),
		);

		$this->assertSame($expectedEditable, $resolved->isEditableByCurrentActor());
	}

	public function testResolveRequestSignGroupsDoesNotBecomeEditableWithoutExplicitGlobalSystemDelegation(): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('system')
			->setValue(RequestSignGroupsPolicyValue::encode(['board']))
			->setAllowChildOverride(true)
			->setVisibleToChild(true);
		$source->groupLayers = [
			(new PolicyLayer())
				->setScope('group')
				->setValue(RequestSignGroupsPolicyValue::encode(['board']))
				->setAllowChildOverride(true)
				->setVisibleToChild(true),
		];

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve(
			$this->getRequestSignGroupsDefinition(),
			PolicyContext::fromUserId('ceo')->setActorCapabilities([
				'canManageSystemPolicies' => false,
				'canManageGroupPolicies' => true,
				'manageableGroupCount' => 1,
			]),
		);

		$this->assertFalse($resolved->isEditableByCurrentActor());
	}

	public function testResolveRequestSignGroupsBecomesEditableFromExplicitGlobalSystemGrantAlone(): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('global')
			->setValue(RequestSignGroupsPolicyValue::encode(['company']))
			->setAllowChildOverride(true)
			->setVisibleToChild(true);

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve(
			$this->getRequestSignGroupsDefinition(),
			PolicyContext::fromUserId('ceo')->setActorCapabilities([
				'canManageSystemPolicies' => false,
				'canManageGroupPolicies' => true,
				'manageableGroupCount' => 1,
			]),
		);

		$this->assertTrue($resolved->isEditableByCurrentActor());
	}

	public function testResolveRequestSignGroupsDoesNotBecomeEditableFromGroupLayerAlone(): void {
		$source = new InMemoryPolicySource();
		$source->groupLayers = [
			(new PolicyLayer())
				->setScope('group')
				->setValue(RequestSignGroupsPolicyValue::encode(['board']))
				->setAllowChildOverride(true)
				->setVisibleToChild(true),
		];

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve(
			$this->getRequestSignGroupsDefinition(),
			PolicyContext::fromUserId('ceo')->setActorCapabilities([
				'canManageSystemPolicies' => false,
				'canManageGroupPolicies' => true,
				'manageableGroupCount' => 2,
			]),
		);

		$this->assertFalse($resolved->isEditableByCurrentActor());
	}

	public function testResolveRequestSignGroupsBecomesEditableFromSystemCreatedGroupLayerDelegation(): void {
		$source = new InMemoryPolicySource();
		$source->groupLayers = [
			(new PolicyLayer())
				->setScope('group')
				->setValue(RequestSignGroupsPolicyValue::encode(['board']))
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setNotes([
					'createdBySystemAdmin' => true,
					'createdByActorScope' => 'system',
				]),
		];

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve(
			$this->getRequestSignGroupsDefinition(),
			PolicyContext::fromUserId('ceo')->setActorCapabilities([
				'canManageSystemPolicies' => false,
				'canManageGroupPolicies' => true,
				'manageableGroupCount' => 1,
			]),
		);

		$this->assertTrue($resolved->isEditableByCurrentActor());
	}

	public function testResolveRequestSignGroupsKeepsEditableWhenDelegatedOverrideOverlaysSystemCreatedSeed(): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('system')
			->setValue(RequestSignGroupsPolicyValue::encode([
				'allowGroups' => ['admin'],
				'denyGroups' => [],
			]))
			->setAllowChildOverride(true)
			->setVisibleToChild(true);
		$source->groupLayers = [
			(new PolicyLayer())
				->setScope('group')
				->setValue(RequestSignGroupsPolicyValue::encode([
					'allowGroups' => ['board'],
					'denyGroups' => ['board'],
				]))
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setNotes([
					'createdBySystemAdmin' => false,
					'createdByActorScope' => 'group',
					'delegatedFromSystemCreatedSeed' => true,
				]),
		];

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve(
			$this->getRequestSignGroupsDefinition(),
			PolicyContext::fromUserId('ceo')->setActorCapabilities([
				'canManageSystemPolicies' => false,
				'canManageGroupPolicies' => true,
				'manageableGroupCount' => 1,
			]),
		);

		$this->assertSame(
			RequestSignGroupsPolicyValue::encode([
				'allowGroups' => ['board'],
				'denyGroups' => ['board'],
			]),
			$resolved->getEffectiveValue(),
		);
		$this->assertSame('group', $resolved->getSourceScope());
		$this->assertTrue($resolved->isEditableByCurrentActor());
	}

	/** @return array<string, array{0: int, 1: bool}> */
	public static function provideRequestSignGroupsEditableByManageableGroupCountCases(): array {
		return [
			'cannot edit with zero manageable groups' => [0, false],
			'can edit with exactly one manageable group' => [1, true],
			'can edit with exactly two manageable groups' => [2, true],
			'can edit with more than two manageable groups' => [3, true],
		];
	}

	#[DataProvider('provideEditableByActorCases')]
	public function testResolveActorPermissionFlagsRespectCapabilitiesDefinitionSupportAndHierarchy(
		array $actorCapabilities,
		bool $supportsGroupAdminConfiguration,
		string $systemLayerScope,
		bool $allowChildOverride,
		bool $visibleToChild,
		bool $expectedEditable,
		bool $expectedCanSave,
		bool $expectedCanOverride,
	): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope($systemLayerScope)
			->setValue('ordered_numeric')
			->setAllowChildOverride($allowChildOverride)
			->setVisibleToChild($visibleToChild)
			->setAllowedValues(['ordered_numeric']);

		$context = PolicyContext::fromUserId('manager')
			->setActorCapabilities($actorCapabilities);

		$definition = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
			supportsGroupAdminConfiguration: $supportsGroupAdminConfiguration,
		);

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve($definition, $context);

		$this->assertSame($expectedEditable, $resolved->isEditableByCurrentActor());
		$this->assertSame($expectedCanSave, $resolved->canSaveAsUserDefault());
		$this->assertSame($expectedCanOverride, $resolved->canUseAsRequestOverride());
	}

	/** @return array<string, array{0: array<string, bool>, 1: bool, 2: string, 3: bool, 4: bool, 5: bool, 6: bool, 7: bool}> */
	public static function provideEditableByActorCases(): array {
		// Dataset order:
		// [actorCapabilities, supportsGroupAdminConfiguration, systemLayerScope,
		//  allowChildOverride, visibleToChild, expectedEditable,
		//  expectedCanSaveAsUserDefault, expectedCanUseAsRequestOverride]
		return [
			// --- system admin scenarios ---
			'system admin can edit and users can also override when hierarchy permits' => [
				['canManageSystemPolicies' => true, 'canManageGroupPolicies' => false],
				false, 'system', true, true,
				true, true, true,
			],
			'system admin can edit but hierarchy prevents user overrides' => [
				['canManageSystemPolicies' => true, 'canManageGroupPolicies' => true],
				false, 'global', false, true,
				true, false, false,
			],
			'system admin cannot edit when policy not visible to children' => [
				['canManageSystemPolicies' => true, 'canManageGroupPolicies' => true],
				true, 'system', true, false,
				false, false, false,
			],

			// --- group admin scenarios ---
			// scope='global' means the system admin explicitly configured allowChildOverride=true
			'group admin can edit when system admin explicitly grants delegation' => [
				['canManageSystemPolicies' => false, 'canManageGroupPolicies' => true],
				true, 'global', true, true,
				true, true, true,
			],
			// scope='system' means no explicit system config — group admin is closed by default
			'group admin cannot edit or self-override without explicit system grant' => [
				['canManageSystemPolicies' => false, 'canManageGroupPolicies' => true],
				true, 'system', true, true,
				false, false, false,
			],
			'group admin cannot edit when system blocks child overrides' => [
				['canManageSystemPolicies' => false, 'canManageGroupPolicies' => true],
				true, 'global', false, true,
				false, false, false,
			],
			'group admin cannot edit system-only policy but users can still override when hierarchy allows' => [
				['canManageSystemPolicies' => false, 'canManageGroupPolicies' => true],
				false, 'global', true, true,
				false, true, true,
			],
			'group admin cannot edit system-only policy and hierarchy also blocks user overrides' => [
				['canManageSystemPolicies' => false, 'canManageGroupPolicies' => true],
				false, 'global', false, true,
				false, false, false,
			],
			'group admin cannot edit when policy not visible to children' => [
				['canManageSystemPolicies' => false, 'canManageGroupPolicies' => true],
				true, 'global', true, false,
				false, false, false,
			],

			// --- regular user scenarios ---
			'regular user cannot save without explicit system grant' => [
				[],
				true, 'system', true, true,
				false, false, false,
			],
			'regular user can save when system explicitly grants delegation' => [
				[],
				true, 'global', true, true,
				false, true, true,
			],
			'regular user cannot save when hierarchy blocks child overrides' => [
				[],
				true, 'global', false, true,
				false, false, false,
			],
			'regular user cannot save when policy not visible' => [
				[],
				true, 'system', true, false,
				false, false, false,
			],
		];
	}

	public function testResolveIgnoresCircleLayersInCurrentPhase(): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('system')
			->setValue('none')
			->setAllowChildOverride(true)
			->setVisibleToChild(true);
		$source->groupLayers = [
			(new PolicyLayer())
				->setScope('group')
				->setValue('parallel')
				->setAllowChildOverride(true)
				->setVisibleToChild(true),
		];
		$source->circleLayers = [
			(new PolicyLayer())
				->setScope('circle')
				->setValue('ordered_numeric')
				->setAllowChildOverride(true)
				->setVisibleToChild(true),
		];

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve($this->getDefinition(), PolicyContext::fromUserId('john'));

		$this->assertSame('parallel', $resolved->getEffectiveValue());
		$this->assertFalse($source->circlePoliciesLoaded);
	}

	public function testResolveManyUsesBulkLoadingAndResolvesAllDefinitions(): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('system')
			->setValue('none')
			->setAllowChildOverride(true)
			->setVisibleToChild(true);
		$source->groupLayers = [
			(new PolicyLayer())
				->setScope('group')
				->setValue('ordered_numeric')
				->setAllowChildOverride(true)
				->setVisibleToChild(true)
				->setAllowedValues(['parallel', 'ordered_numeric']),
		];

		// Both definitions share the same allowed value set so the group layer applies to both
		$definitions = [
			new PolicySpec(key: 'signature_flow', defaultSystemValue: 'none', allowedValues: ['none', 'parallel', 'ordered_numeric']),
			new PolicySpec(key: 'alt_policy', defaultSystemValue: 'none', allowedValues: ['none', 'parallel', 'ordered_numeric']),
		];

		$resolver = new DefaultPolicyResolver($source);
		$results = $resolver->resolveMany($definitions, PolicyContext::fromUserId('john'));

		$this->assertArrayHasKey('signature_flow', $results);
		$this->assertArrayHasKey('alt_policy', $results);
		$this->assertSame('ordered_numeric', $results['signature_flow']->getEffectiveValue());
		$this->assertSame('ordered_numeric', $results['alt_policy']->getEffectiveValue());
		$this->assertTrue($source->bulkGroupPoliciesLoaded);
		$this->assertTrue($source->bulkUserPrefsLoaded);
	}

	private function getDefinition(): PolicySpec {
		return new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
		);
	}

	private function getRequestSignGroupsDefinition(): IPolicyDefinition {
		return (new RequestSignGroupsPolicy())->get(RequestSignGroupsPolicy::KEY);
	}

	public function testResolveCanSaveAsUserDefaultFalseWhenDefinitionDoesNotSupportUserPreference(): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('system')
			->setValue('none')
			->setAllowChildOverride(true)
			->setVisibleToChild(true);

		$definition = new PolicySpec(
			key: 'admin_only_policy',
			defaultSystemValue: 'none',
			allowedValues: ['none', 'strict'],
			supportsUserPreference: false,
		);

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve($definition, PolicyContext::fromUserId('john'));

		$this->assertFalse($resolved->canSaveAsUserDefault(), 'canSaveAsUserDefault must be false when supportsUserPreference() returns false');
		$this->assertFalse($resolved->canUseAsRequestOverride());
	}

	#[DataProvider('provideUserPreferenceSupportCases')]
	public function testResolveUserPreferenceFlagsFollowVisibilityOverrideAndDefinitionSupport(
		bool $supportsUserPreference,
		string $systemLayerScope,
		bool $allowChildOverride,
		bool $visibleToChild,
		bool $expected,
	): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope($systemLayerScope)
			->setValue('none')
			->setAllowChildOverride($allowChildOverride)
			->setVisibleToChild($visibleToChild);

		$definition = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
			supportsUserPreference: $supportsUserPreference,
		);

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve($definition, PolicyContext::fromUserId('john'));

		$this->assertSame($expected, $resolved->canSaveAsUserDefault());
		$this->assertSame($expected, $resolved->canUseAsRequestOverride());
	}

	/** @return array<string, array{0: bool, 1: string, 2: bool, 3: bool, 4: bool}> */
	public static function provideUserPreferenceSupportCases(): array {
		return [
			'definition supports preferences and explicit grant allows override' => [
				true,
				'global',
				true,
				true,
				true,
			],
			'definition supports preferences but implicit default does not grant override' => [
				true,
				'system',
				true,
				true,
				false,
			],
			'definition supports preferences but system blocks override' => [
				true,
				'global',
				false,
				true,
				false,
			],
			'definition supports preferences but policy is not visible' => [
				true,
				'global',
				true,
				false,
				false,
			],
			'definition disables preferences even when override is allowed' => [
				false,
				'global',
				true,
				true,
				false,
			],
			'definition disables preferences and system blocks override' => [
				false,
				'global',
				false,
				true,
				false,
			],
			'definition disables preferences and policy is not visible' => [
				false,
				'global',
				true,
				false,
				false,
			],
		];
	}

	public function testResolveAllowsUserPreferenceWhenManagedGroupLayerPermitsOverrideWithoutExplicitSystemGrant(): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('system')
			->setValue('none')
			->setAllowChildOverride(true)
			->setVisibleToChild(true);
		$source->groupLayers = [
			(new PolicyLayer())
				->setScope('group')
				->setValue('parallel')
				->setAllowChildOverride(true)
				->setVisibleToChild(true),
		];

		$definition = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
			supportsUserPreference: true,
		);

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve($definition, PolicyContext::fromUserId('john'));

		$this->assertSame('group', $resolved->getSourceScope());
		$this->assertTrue($resolved->canSaveAsUserDefault());
		$this->assertTrue($resolved->canUseAsRequestOverride());
	}

	public function testResolvePropagatesResolvedStateMetaFromDefinition(): void {
		$source = new InMemoryPolicySource();
		$definition = new PolicySpec(
			key: 'signature_stamp',
			defaultSystemValue: 'none',
			allowedValues: [],
			resolvedStateMeta: static fn (PolicyContext $context): array => [
				'defaultSystemValue' => 'canonical-' . $context->getUserId(),
			],
		);

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve($definition, PolicyContext::fromUserId('john'));

		$this->assertSame(['defaultSystemValue' => 'canonical-john'], $resolved->getMeta());
	}

	private function getValueChoiceDefinition(): PolicySpec {
		return new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
			resolutionMode: PolicySpec::RESOLUTION_MODE_VALUE_CHOICE,
		);
	}
}

final class InMemoryPolicySource implements IPolicySource {
	public ?PolicyLayer $systemLayer = null;
	/** @var list<PolicyLayer> */
	public array $groupLayers = [];
	/** @var list<PolicyLayer> */
	public array $circleLayers = [];
	public ?PolicyLayer $userPolicy = null;
	public ?PolicyLayer $userPreference = null;
	public ?PolicyLayer $requestOverride = null;
	public bool $userPreferenceCleared = false;
	public bool $circlePoliciesLoaded = false;
	public bool $bulkGroupPoliciesLoaded = false;
	public bool $bulkUserPoliciesLoaded = false;
	public bool $bulkUserPrefsLoaded = false;

	public function loadSystemPolicy(string $policyKey): ?PolicyLayer {
		return $this->systemLayer;
	}

	public function loadGroupPolicies(string $policyKey, PolicyContext $context): array {
		return $this->groupLayers;
	}

	public function loadGroupPolicyConfig(string $policyKey, string $groupId): ?PolicyLayer {
		return $this->groupLayers[0] ?? null;
	}

	/** @return list<array{targetId: string, policy: PolicyLayer}> */
	public function listGroupPoliciesByKey(string $policyKey): array {
		return [];
	}

	/** @return list<array{targetId: string, policy: PolicyLayer}> */
	public function listGroupPoliciesByKeyForTargets(string $policyKey, array $groupIds): array {
		return [];
	}

	public function loadCirclePolicies(string $policyKey, PolicyContext $context): array {
		$this->circlePoliciesLoaded = true;
		return $this->circleLayers;
	}

	public function loadUserPolicy(string $policyKey, PolicyContext $context): ?PolicyLayer {
		return $this->userPolicy;
	}

	public function loadUserPreference(string $policyKey, PolicyContext $context): ?PolicyLayer {
		return $this->userPreference;
	}

	public function loadRequestOverride(string $policyKey, PolicyContext $context): ?PolicyLayer {
		return $this->requestOverride;
	}

	/** @param list<string> $policyKeys */
	public function loadAllGroupPolicies(array $policyKeys, PolicyContext $context): array {
		$this->bulkGroupPoliciesLoaded = true;
		return array_fill_keys($policyKeys, $this->groupLayers);
	}

	/** @param list<string> $policyKeys */
	public function loadAllUserPolicies(array $policyKeys, PolicyContext $context): array {
		$this->bulkUserPoliciesLoaded = true;
		if ($this->userPolicy === null) {
			return [];
		}
		return array_fill_keys($policyKeys, $this->userPolicy);
	}

	/** @param list<string> $policyKeys */
	public function loadAllUserPreferences(array $policyKeys, PolicyContext $context): array {
		$this->bulkUserPrefsLoaded = true;
		if ($this->userPreference === null) {
			return [];
		}
		return array_fill_keys($policyKeys, $this->userPreference);
	}

	public function saveSystemPolicy(string $policyKey, mixed $value, bool $allowChildOverride = false): void {
	}

	public function clearSystemPolicy(string $policyKey): void {
	}

	public function saveGroupPolicy(string $policyKey, string $groupId, mixed $value, bool $allowChildOverride, bool $createdBySystemAdmin = false): void {
	}

	public function loadUserPolicyConfig(string $policyKey, string $userId): ?PolicyLayer {
		return $this->userPolicy;
	}

	/** @return list<array{targetId: string, policy: PolicyLayer}> */
	public function listUserPoliciesByKey(string $policyKey): array {
		return [];
	}

	public function saveUserPreference(string $policyKey, PolicyContext $context, mixed $value): void {
	}

	public function saveUserPolicy(string $policyKey, PolicyContext $context, mixed $value, bool $allowChildOverride): void {
	}

	public function clearGroupPolicy(string $policyKey, string $groupId, bool $preserveSystemCreatedBase = false): void {
	}

	public function clearUserPreference(string $policyKey, PolicyContext $context): void {
		$this->userPreferenceCleared = true;
	}

	public function clearUserPolicy(string $policyKey, PolicyContext $context): void {
	}
}
