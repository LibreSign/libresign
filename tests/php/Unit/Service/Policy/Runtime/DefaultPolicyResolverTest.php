<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Runtime;

use OCA\Libresign\Service\Policy\Contract\IPolicySource;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Runtime\DefaultPolicyResolver;
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

	public function testResolveMarksPolicyEditableForSystemAdminEvenWhenChildrenCannotOverride(): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('system')
			->setValue('ordered_numeric')
			->setAllowChildOverride(false)
			->setVisibleToChild(true)
			->setAllowedValues(['ordered_numeric']);

		$context = PolicyContext::fromUserId('admin')
			->setActorCapabilities([
				'canManageSystemPolicies' => true,
				'canManageGroupPolicies' => true,
			]);

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve($this->getDefinition(), $context);

		$this->assertTrue($resolved->isEditableByCurrentActor());
		$this->assertFalse($resolved->canSaveAsUserDefault());
		$this->assertFalse($resolved->canUseAsRequestOverride());
	}

	public function testResolveMarksPolicyEditableForGroupAdminEvenWhenChildrenCannotOverride(): void {
		$source = new InMemoryPolicySource();
		$source->systemLayer = (new PolicyLayer())
			->setScope('system')
			->setValue('ordered_numeric')
			->setAllowChildOverride(false)
			->setVisibleToChild(true)
			->setAllowedValues(['ordered_numeric']);

		$context = PolicyContext::fromUserId('manager')
			->setActorCapabilities([
				'canManageSystemPolicies' => false,
				'canManageGroupPolicies' => true,
			]);

		$resolver = new DefaultPolicyResolver($source);
		$resolved = $resolver->resolve($this->getDefinition(), $context);

		$this->assertTrue($resolved->isEditableByCurrentActor());
		$this->assertFalse($resolved->canSaveAsUserDefault());
		$this->assertFalse($resolved->canUseAsRequestOverride());
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
	public ?PolicyLayer $userPreference = null;
	public ?PolicyLayer $requestOverride = null;
	public bool $userPreferenceCleared = false;
	public bool $circlePoliciesLoaded = false;
	public bool $bulkGroupPoliciesLoaded = false;
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

	public function loadCirclePolicies(string $policyKey, PolicyContext $context): array {
		$this->circlePoliciesLoaded = true;
		return $this->circleLayers;
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
	public function loadAllUserPreferences(array $policyKeys, PolicyContext $context): array {
		$this->bulkUserPrefsLoaded = true;
		if ($this->userPreference === null) {
			return [];
		}
		return array_fill_keys($policyKeys, $this->userPreference);
	}

	public function saveSystemPolicy(string $policyKey, mixed $value, bool $allowChildOverride = false): void {
	}

	public function saveGroupPolicy(string $policyKey, string $groupId, mixed $value, bool $allowChildOverride): void {
	}

	public function saveUserPreference(string $policyKey, PolicyContext $context, mixed $value): void {
	}

	public function clearGroupPolicy(string $policyKey, string $groupId): void {
	}

	public function clearUserPreference(string $policyKey, PolicyContext $context): void {
		$this->userPreferenceCleared = true;
	}
}
