<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy;

use OCA\Libresign\Service\Policy\DefaultPolicyResolver;
use OCA\Libresign\Service\Policy\PolicyContext;
use OCA\Libresign\Service\Policy\PolicyDefinitionInterface;
use OCA\Libresign\Service\Policy\PolicyLayer;
use OCA\Libresign\Service\Policy\PolicySourceInterface;
use PHPUnit\Framework\TestCase;

final class DefaultPolicyResolverTest extends TestCase {
	public function testResolveUsesDefinitionDefaultWhenNoLayersExist(): void {
		$resolver = new DefaultPolicyResolver(
			new InMemoryPolicySource(),
			[new TestPolicyDefinition()]
		);

		$resolved = $resolver->resolve('signature_flow', new PolicyContext());

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

		$resolver = new DefaultPolicyResolver($source, [new TestPolicyDefinition()]);
		$resolved = $resolver->resolve('signature_flow', PolicyContext::fromUserId('john'));

		$this->assertSame('ordered_numeric', $resolved->getEffectiveValue());
		$this->assertSame('group', $resolved->getSourceScope());
		$this->assertTrue($resolved->isEditableByCurrentActor());
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

		$resolver = new DefaultPolicyResolver($source, [new TestPolicyDefinition()]);
		$resolved = $resolver->resolve('signature_flow', PolicyContext::fromUserId('john'));

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

		$resolver = new DefaultPolicyResolver($source, [new TestPolicyDefinition()]);
		$resolved = $resolver->resolve('signature_flow', PolicyContext::fromUserId('john'));

		$this->assertSame('ordered_numeric', $resolved->getEffectiveValue());
		$this->assertSame('request', $resolved->getSourceScope());
		$this->assertTrue($resolved->canUseAsRequestOverride());
		$this->assertNull($resolved->getBlockedBy());
	}
}

final class InMemoryPolicySource implements PolicySourceInterface {
	public ?PolicyLayer $systemLayer = null;
	/** @var list<PolicyLayer> */
	public array $groupLayers = [];
	public ?PolicyLayer $userPreference = null;
	public ?PolicyLayer $requestOverride = null;
	public bool $userPreferenceCleared = false;

	public function loadSystemPolicy(string $policyKey): ?PolicyLayer {
		return $this->systemLayer;
	}

	public function loadGroupPolicies(string $policyKey, PolicyContext $context): array {
		return $this->groupLayers;
	}

	public function loadCirclePolicies(string $policyKey, PolicyContext $context): array {
		return [];
	}

	public function loadUserPreference(string $policyKey, PolicyContext $context): ?PolicyLayer {
		return $this->userPreference;
	}

	public function loadRequestOverride(string $policyKey, PolicyContext $context): ?PolicyLayer {
		return $this->requestOverride;
	}

	public function clearUserPreference(string $policyKey, PolicyContext $context): void {
		$this->userPreferenceCleared = true;
	}
}

final class TestPolicyDefinition implements PolicyDefinitionInterface {
	public function key(): string {
		return 'signature_flow';
	}

	public function normalizeValue(mixed $rawValue): mixed {
		return $rawValue;
	}

	public function validateValue(mixed $value): void {
		if (!in_array($value, $this->allowedValues(new PolicyContext()), true)) {
			throw new \InvalidArgumentException('Invalid value');
		}
	}

	public function allowedValues(PolicyContext $context): array {
		return ['none', 'parallel', 'ordered_numeric'];
	}

	public function defaultSystemValue(): mixed {
		return 'none';
	}
}
