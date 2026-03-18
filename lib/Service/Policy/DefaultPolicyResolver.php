<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

final class DefaultPolicyResolver implements PolicyResolverInterface {
	/** @var array<string, PolicyDefinitionInterface> */
	private array $definitions = [];

	/** @param iterable<PolicyDefinitionInterface> $definitions */
	public function __construct(
		private PolicySourceInterface $source,
		iterable $definitions,
	) {
		foreach ($definitions as $definition) {
			$this->definitions[$definition->key()] = $definition;
		}
	}

	#[\Override]
	public function resolve(string $policyKey, PolicyContext $context): ResolvedPolicy {
		$definition = $this->definitions[$policyKey] ?? null;
		if ($definition === null) {
			throw new \InvalidArgumentException(sprintf('Unknown policy key: %s', $policyKey));
		}

		$resolved = (new ResolvedPolicy())
			->setPolicyKey($policyKey)
			->setAllowedValues($definition->allowedValues($context));

		$systemLayer = $this->source->loadSystemPolicy($policyKey);
		$groupLayers = $this->source->loadGroupPolicies($policyKey, $context);
		$circleLayers = $this->source->loadCirclePolicies($policyKey, $context);

		$currentValue = $definition->defaultSystemValue();
		$currentSourceScope = 'system';
		$currentBlockedBy = null;
		$canOverrideBelow = false;
		$visible = true;

		if ($systemLayer !== null) {
			[$currentValue, $currentSourceScope, $canOverrideBelow, $visible] = $this->applyLayer(
				$definition,
				$resolved,
				$systemLayer,
				$currentValue,
				$currentSourceScope,
				true,
				$visible,
			);
		}

		foreach (array_merge($groupLayers, $circleLayers) as $layer) {
			[$currentValue, $currentSourceScope, $canOverrideBelow, $visible] = $this->applyLayer(
				$definition,
				$resolved,
				$layer,
				$currentValue,
				$currentSourceScope,
				$canOverrideBelow,
				$visible,
			);
		}

		$userPreference = $this->source->loadUserPreference($policyKey, $context);
		if ($userPreference !== null) {
			if ($this->canApplyLowerLayer($definition, $resolved, $userPreference, $canOverrideBelow, $visible)) {
				$currentValue = $definition->normalizeValue($userPreference->getValue());
				$definition->validateValue($currentValue);
				$currentSourceScope = $userPreference->getScope();
			} else {
				$this->source->clearUserPreference($policyKey, $context);
				$currentBlockedBy = $currentSourceScope;
				$resolved->setPreferenceWasCleared(true);
			}
		}

		$requestOverride = $this->source->loadRequestOverride($policyKey, $context);
		if ($requestOverride !== null) {
			if ($this->canApplyLowerLayer($definition, $resolved, $requestOverride, $canOverrideBelow, $visible)) {
				$currentValue = $definition->normalizeValue($requestOverride->getValue());
				$definition->validateValue($currentValue);
				$currentSourceScope = $requestOverride->getScope();
			} elseif ($currentBlockedBy === null) {
				$currentBlockedBy = $currentSourceScope;
			}
		}

		$resolved
			->setEffectiveValue($currentValue)
			->setSourceScope($currentSourceScope)
			->setVisible($visible)
			->setEditableByCurrentActor($visible && $canOverrideBelow)
			->setCanSaveAsUserDefault($visible && $canOverrideBelow)
			->setCanUseAsRequestOverride($visible && $canOverrideBelow)
			->setBlockedBy($currentBlockedBy);

		return $resolved;
	}

	#[\Override]
	public function resolveMany(array $policyKeys, PolicyContext $context): array {
		$resolved = [];
		foreach ($policyKeys as $policyKey) {
			$resolved[$policyKey] = $this->resolve($policyKey, $context);
		}
		return $resolved;
	}

	private function applyLayer(
		PolicyDefinitionInterface $definition,
		ResolvedPolicy $resolved,
		PolicyLayer $layer,
		mixed $currentValue,
		string $currentSourceScope,
		bool $canOverrideBelow,
		bool $visible,
	): array {
		$visible = $visible && $layer->isVisibleToChild();
		$resolved->setAllowedValues($this->mergeAllowedValues($resolved->getAllowedValues(), $layer->getAllowedValues()));

		if ($layer->getValue() !== null && ($currentSourceScope === 'system' || $canOverrideBelow)) {
			$currentValue = $definition->normalizeValue($layer->getValue());
			$definition->validateValue($currentValue);
			$currentSourceScope = $layer->getScope();
		}

		$canOverrideBelow = $layer->isAllowChildOverride();

		return [$currentValue, $currentSourceScope, $canOverrideBelow, $visible];
	}

	private function canApplyLowerLayer(
		PolicyDefinitionInterface $definition,
		ResolvedPolicy $resolved,
		PolicyLayer $layer,
		bool $canOverrideBelow,
		bool $visible,
	): bool {
		if (!$visible || !$canOverrideBelow || $layer->getValue() === null) {
			return false;
		}

		$value = $definition->normalizeValue($layer->getValue());
		$allowedValues = $resolved->getAllowedValues();
		if ($allowedValues !== [] && !in_array($value, $allowedValues, true)) {
			return false;
		}

		$definition->validateValue($value);
		return true;
	}

	/** @param list<mixed> $currentAllowedValues
	 * @param list<mixed> $layerAllowedValues
	 * @return list<mixed>
	 */
	private function mergeAllowedValues(array $currentAllowedValues, array $layerAllowedValues): array {
		if ($layerAllowedValues === []) {
			return $currentAllowedValues;
		}

		if ($currentAllowedValues === []) {
			return $layerAllowedValues;
		}

		return array_values(array_intersect($currentAllowedValues, $layerAllowedValues));
	}
}
