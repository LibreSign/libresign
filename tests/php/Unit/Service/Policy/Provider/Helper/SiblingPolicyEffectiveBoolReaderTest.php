<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Helper;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Model\ActiveGroupScope;
use OCA\Libresign\Service\Policy\Model\ActorRole;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Provider\Helper\SiblingPolicyEffectiveBoolReader;
use OCA\Libresign\Service\Policy\Provider\ObserverProfile\ObserverProfilePolicy;
use OCA\Libresign\Service\Policy\Provider\ValidationAccess\ValidationAccessPolicy;
use OCA\Libresign\Service\Policy\Runtime\DefaultPolicyResolver;
use OCA\Libresign\Service\Policy\Runtime\PolicyRegistry;
use OCA\Libresign\Service\Policy\Runtime\PolicySource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class SiblingPolicyEffectiveBoolReaderTest extends TestCase {
	private PolicySource&MockObject $source;
	private ?SiblingPolicyEffectiveBoolReader $reader = null;
	/** @var array<string, IPolicyDefinition> */
	private array $definitions = [];

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$this->reader = null;
		$this->definitions = [];
		$this->source = $this->createMock(PolicySource::class);
		$this->source->method('loadCirclePolicies')->willReturn([]);
		$this->source->method('loadUserPolicy')->willReturn(null);
		$this->source->method('loadUserPreference')->willReturn(null);
		$this->source->method('loadRequestOverride')->willReturn(null);
		$this->source->method('loadAllGroupPolicies')->willReturn([]);
		$this->source->method('loadAllUserPolicies')->willReturn([]);
		$this->source->method('loadAllUserPreferences')->willReturn([]);
	}

	public function testGetEffectiveBoolFollowsTargetGroupContextNotAdminPersonalLayers(): void {
		$this->definitions[ValidationAccessPolicy::KEY] = $this->boolPolicy(ValidationAccessPolicy::KEY);
		$this->stubValidationAccessLayers();

		$reader = $this->createReader();
		$adminPersonalContext = $this->adminPersonalContext();
		$targetGroupContext = $this->targetFinanceGroupContext();

		$this->assertFalse(
			$reader->getEffectiveBool(ValidationAccessPolicy::KEY, $adminPersonalContext),
			'Admin personal/system effective value must stay public',
		);
		$this->assertTrue(
			$reader->getEffectiveBool(ValidationAccessPolicy::KEY, $targetGroupContext),
			'Target finance group effective value must be private',
		);
	}

	public function testObserverWarningMetaFollowsTargetScopeSibling(): void {
		$this->definitions[ValidationAccessPolicy::KEY] = $this->boolPolicy(ValidationAccessPolicy::KEY);
		$this->definitions[ObserverProfilePolicy::KEY] = $this->boolPolicy(
			ObserverProfilePolicy::KEY,
			resolvedStateMeta: function (PolicyContext $context): array {
				return [
					'validationUrlIsPrivate' => $this->createReader()->getEffectiveBool(
						ValidationAccessPolicy::KEY,
						$context,
					),
				];
			},
		);

		$this->source
			->method('loadSystemPolicy')
			->willReturnCallback(static function (string $policyKey): ?PolicyLayer {
				return match ($policyKey) {
					ValidationAccessPolicy::KEY => (new PolicyLayer())
						->setScope('system')
						->setValue(false)
						->setAllowChildOverride(true)
						->setVisibleToChild(true),
					ObserverProfilePolicy::KEY => (new PolicyLayer())
						->setScope('system')
						->setValue(true)
						->setAllowChildOverride(true)
						->setVisibleToChild(true),
					default => null,
				};
			});

		$this->source
			->method('loadGroupPolicies')
			->willReturnCallback(static function (string $policyKey, PolicyContext $context): array {
				if ($policyKey !== ValidationAccessPolicy::KEY) {
					return [];
				}

				if (!in_array('finance', $context->getGroups(), true)) {
					return [];
				}

				return [
					(new PolicyLayer())
						->setScope('group')
						->setValue(true)
						->setAllowChildOverride(true)
						->setVisibleToChild(true),
				];
			});

		$resolver = new DefaultPolicyResolver($this->source);
		$adminResolved = $resolver->resolve(
			$this->definitions[ObserverProfilePolicy::KEY],
			$this->adminPersonalContext(),
		);
		$groupResolved = $resolver->resolve(
			$this->definitions[ObserverProfilePolicy::KEY],
			$this->targetFinanceGroupContext(),
		);

		$this->assertFalse($adminResolved->getMeta()['validationUrlIsPrivate']);
		$this->assertTrue($groupResolved->getMeta()['validationUrlIsPrivate']);
	}

	public function testRecursionGuardPreventsMutualSiblingMetaCycles(): void {
		$this->source->method('loadSystemPolicy')->willReturn(null);
		$this->source->method('loadGroupPolicies')->willReturn([]);

		$this->definitions[ValidationAccessPolicy::KEY] = $this->boolPolicy(
			ValidationAccessPolicy::KEY,
			defaultSystemValue: true,
			resolvedStateMeta: function (PolicyContext $context): array {
				return [
					'observerProfileEnabled' => $this->createReader()->getEffectiveBool(
						ObserverProfilePolicy::KEY,
						$context,
					),
				];
			},
		);
		$this->definitions[ObserverProfilePolicy::KEY] = $this->boolPolicy(
			ObserverProfilePolicy::KEY,
			defaultSystemValue: true,
			resolvedStateMeta: function (PolicyContext $context): array {
				return [
					'validationUrlIsPrivate' => $this->createReader()->getEffectiveBool(
						ValidationAccessPolicy::KEY,
						$context,
					),
				];
			},
		);

		$reader = $this->createReader();
		$context = new PolicyContext();
		$resolver = new DefaultPolicyResolver($this->source);

		$this->assertTrue($reader->getEffectiveBool(ValidationAccessPolicy::KEY, $context));
		$this->assertTrue($reader->getEffectiveBool(ObserverProfilePolicy::KEY, $context));

		$validationResolved = $resolver->resolve($this->definitions[ValidationAccessPolicy::KEY], $context);
		$observerResolved = $resolver->resolve($this->definitions[ObserverProfilePolicy::KEY], $context);

		$this->assertTrue($validationResolved->getEffectiveValueAsBool());
		$this->assertTrue($observerResolved->getEffectiveValueAsBool());
		$this->assertTrue($validationResolved->getMeta()['observerProfileEnabled']);
		$this->assertTrue($observerResolved->getMeta()['validationUrlIsPrivate']);
	}

	/**
	 * @param array<string, mixed>|(\Closure(PolicyContext): array<string, mixed>) $resolvedStateMeta
	 */
	private function boolPolicy(
		string $key,
		bool $defaultSystemValue = false,
		array|\Closure $resolvedStateMeta = [],
	): PolicySpec {
		return new PolicySpec(
			key: $key,
			defaultSystemValue: $defaultSystemValue,
			allowedValues: [false, true],
			normalizer: static fn (mixed $rawValue): bool => filter_var($rawValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
			resolvedStateMeta: $resolvedStateMeta,
		);
	}

	private function stubValidationAccessLayers(): void {
		$this->source
			->method('loadSystemPolicy')
			->willReturnCallback(static function (string $policyKey): ?PolicyLayer {
				if ($policyKey !== ValidationAccessPolicy::KEY) {
					return null;
				}

				return (new PolicyLayer())
					->setScope('system')
					->setValue(false)
					->setAllowChildOverride(true)
					->setVisibleToChild(true);
			});

		$this->source
			->method('loadGroupPolicies')
			->willReturnCallback(static function (string $policyKey, PolicyContext $context): array {
				if ($policyKey !== ValidationAccessPolicy::KEY) {
					return [];
				}

				if (!in_array('finance', $context->getGroups(), true)) {
					return [];
				}

				return [
					(new PolicyLayer())
						->setScope('group')
						->setValue(true)
						->setAllowChildOverride(true)
						->setVisibleToChild(true),
				];
			});
	}

	private function adminPersonalContext(): PolicyContext {
		return PolicyContext::fromUserId('admin')
			->setGroups(['admins'])
			->setActorRole(ActorRole::systemAdmin());
	}

	private function targetFinanceGroupContext(): PolicyContext {
		return PolicyContext::fromUserId('admin')
			->setGroups(['finance'])
			->setActiveGroupScope(new ActiveGroupScope('finance'))
			->setActorRole(ActorRole::systemAdmin());
	}

	private function createReader(): SiblingPolicyEffectiveBoolReader {
		if ($this->reader instanceof SiblingPolicyEffectiveBoolReader) {
			return $this->reader;
		}

		$definitions = &$this->definitions;
		$container = $this->createMock(ContainerInterface::class);
		$container
			->method('get')
			->willReturnCallback(static function (string $class) use (&$definitions): object {
				$key = match ($class) {
					ValidationAccessPolicy::class => ValidationAccessPolicy::KEY,
					ObserverProfilePolicy::class => ObserverProfilePolicy::KEY,
					default => throw new \InvalidArgumentException('Unexpected provider class: ' . $class),
				};
				$definition = $definitions[$key] ?? null;
				if (!$definition instanceof IPolicyDefinition) {
					throw new \InvalidArgumentException('Unknown policy key: ' . $key);
				}

				return new class ($definition) implements \OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider {
					public function __construct(private IPolicyDefinition $definition) {
					}

					public function keys(): array {
						return [$this->definition->key()];
					}

					public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
						return $this->definition;
					}
				};
			});

		$registry = new PolicyRegistry($container, [
			ValidationAccessPolicy::class,
			ObserverProfilePolicy::class,
		]);

		return $this->reader = new SiblingPolicyEffectiveBoolReader($registry, $this->source);
	}
}
