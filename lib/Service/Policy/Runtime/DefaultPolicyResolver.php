<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Runtime;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyResolver;
use OCA\Libresign\Service\Policy\Contract\IPolicySource;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;

final class DefaultPolicyResolver implements IPolicyResolver {
	public function __construct(
		private IPolicySource $source,
	) {
	}

	#[\Override]
	public function resolve(IPolicyDefinition $definition, PolicyContext $context): ResolvedPolicy {
		$policyKey = $definition->key();
		$resolved = (new ResolvedPolicy())
			->setPolicyKey($policyKey)
			->setAllowedValues($definition->allowedValues($context));

		$systemLayer = $this->source->loadSystemPolicy($policyKey);
		$groupLayers = $this->source->loadGroupPolicies($policyKey, $context);

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
				$context,
				$currentValue,
				$currentSourceScope,
				true,
				$visible,
			);
		}

		if ($definition->resolutionMode() === 'value_choice') {
			[$currentValue, $currentSourceScope, $canOverrideBelow, $visible] = $this->applyValueChoiceGroupLayers(
				$definition,
				$resolved,
				$groupLayers,
				$context,
				$currentValue,
				$currentSourceScope,
				$canOverrideBelow,
				$visible,
			);
		} else {
			foreach ($groupLayers as $layer) {
				[$currentValue, $currentSourceScope, $canOverrideBelow, $visible] = $this->applyLayer(
					$definition,
					$resolved,
					$layer,
					$context,
					$currentValue,
					$currentSourceScope,
					$canOverrideBelow,
					$visible,
				);
			}
		}

		$userPreference = $this->source->loadUserPreference($policyKey, $context);
		if ($userPreference !== null) {
			if ($this->canApplyLowerLayer($definition, $resolved, $userPreference, $canOverrideBelow, $visible, $context)) {
				$currentValue = $definition->normalizeValue($userPreference->getValue());
				$definition->validateValue($currentValue, $context);
				$currentSourceScope = $userPreference->getScope();
			} else {
				$this->source->clearUserPreference($policyKey, $context);
				$currentBlockedBy = $currentSourceScope;
				$resolved->setPreferenceWasCleared(true);
			}
		}

		$requestOverride = $this->source->loadRequestOverride($policyKey, $context);
		if ($requestOverride !== null) {
			if ($this->canApplyLowerLayer($definition, $resolved, $requestOverride, $canOverrideBelow, $visible, $context)) {
				$currentValue = $definition->normalizeValue($requestOverride->getValue());
				$definition->validateValue($currentValue, $context);
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

	/**
	 * @param list<PolicyLayer> $layers
	 * @return array{0: mixed, 1: string, 2: bool, 3: bool}
	 */
	private function applyValueChoiceGroupLayers(
		IPolicyDefinition $definition,
		ResolvedPolicy $resolved,
		array $layers,
		PolicyContext $context,
		mixed $currentValue,
		string $currentSourceScope,
		bool $canOverrideBelow,
		bool $visible,
	): array {
		if ($layers === [] || !$visible || !$canOverrideBelow) {
			return [$currentValue, $currentSourceScope, $canOverrideBelow, $visible];
		}

		$upstreamAllowedValues = $resolved->getAllowedValues();
		$combinedChoices = [];
		$groupDefaultValues = [];
		$hasVisibleLayer = false;

		foreach ($layers as $layer) {
			if (!$layer->isVisibleToChild()) {
				continue;
			}

			$hasVisibleLayer = true;
			$layerChoices = $this->resolveValueChoiceLayerChoices($definition, $layer, $upstreamAllowedValues, $context);
			$combinedChoices = $this->mergeUnionAllowedValues(
				$definition->allowedValues($context),
				$combinedChoices,
				$layerChoices,
			);

			$normalizedDefault = $definition->normalizeValue($layer->getValue());
			if ($layer->getValue() !== null && in_array($normalizedDefault, $combinedChoices, true) && !in_array($normalizedDefault, $groupDefaultValues, true)) {
				$groupDefaultValues[] = $normalizedDefault;
			}
		}

		if (!$hasVisibleLayer || $combinedChoices === []) {
			return [$currentValue, $currentSourceScope, false, $visible && $hasVisibleLayer];
		}

		$resolved->setAllowedValues($combinedChoices);

		return [
			$this->pickValueChoiceDefault($definition, $currentValue, $combinedChoices, $groupDefaultValues, $context),
			'group',
			count($combinedChoices) > 1,
			true,
		];
	}

	#[\Override]
	/** @param list<IPolicyDefinition> $definitions */
	public function resolveMany(array $definitions, PolicyContext $context): array {
		$resolved = [];
		foreach ($definitions as $definition) {
			if (!$definition instanceof IPolicyDefinition) {
				continue;
			}

			$resolved[$definition->key()] = $this->resolve($definition, $context);
		}
		return $resolved;
	}

	private function applyLayer(
		IPolicyDefinition $definition,
		ResolvedPolicy $resolved,
		PolicyLayer $layer,
		PolicyContext $context,
		mixed $currentValue,
		string $currentSourceScope,
		bool $canOverrideBelow,
		bool $visible,
	): array {
		$visible = $visible && $layer->isVisibleToChild();
		$resolved->setAllowedValues($this->mergeAllowedValues($resolved->getAllowedValues(), $layer->getAllowedValues()));

		if ($layer->getValue() !== null && $canOverrideBelow) {
			$currentValue = $definition->normalizeValue($layer->getValue());
			$definition->validateValue($currentValue, $context);
			$currentSourceScope = $layer->getScope();
		}

		$canOverrideBelow = $canOverrideBelow && $layer->isAllowChildOverride();

		return [$currentValue, $currentSourceScope, $canOverrideBelow, $visible];
	}

	private function canApplyLowerLayer(
		IPolicyDefinition $definition,
		ResolvedPolicy $resolved,
		PolicyLayer $layer,
		bool $canOverrideBelow,
		bool $visible,
		PolicyContext $context,
	): bool {
		if (!$visible || !$canOverrideBelow || $layer->getValue() === null) {
			return false;
		}

		$value = $definition->normalizeValue($layer->getValue());
		$allowedValues = $resolved->getAllowedValues();
		if ($allowedValues !== [] && !in_array($value, $allowedValues, true)) {
			return false;
		}

		$definition->validateValue($value, $context);
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

	/**
	 * @param list<mixed> $upstreamAllowedValues
	 * @return list<mixed>
	 */
	private function resolveValueChoiceLayerChoices(
		IPolicyDefinition $definition,
		PolicyLayer $layer,
		array $upstreamAllowedValues,
		PolicyContext $context,
	): array {
		if ($layer->isAllowChildOverride()) {
			$choices = $layer->getAllowedValues() === []
				? $upstreamAllowedValues
				: array_values(array_intersect($upstreamAllowedValues, $layer->getAllowedValues()));

			$defaultValue = $definition->normalizeValue($definition->defaultSystemValue());
			return array_values(array_filter(
				$choices,
				static fn (mixed $choice): bool => $choice !== $defaultValue,
			));
		}

		if ($layer->getValue() === null) {
			return [];
		}

		$value = $definition->normalizeValue($layer->getValue());
		if ($upstreamAllowedValues !== [] && !in_array($value, $upstreamAllowedValues, true)) {
			return [];
		}

		$definition->validateValue($value, $context);
		return [$value];
	}

	/**
	 * @param list<mixed> $canonicalOrder
	 * @param list<mixed> $currentValues
	 * @param list<mixed> $newValues
	 * @return list<mixed>
	 */
	private function mergeUnionAllowedValues(array $canonicalOrder, array $currentValues, array $newValues): array {
		$merged = [];
		foreach ($canonicalOrder as $candidate) {
			if ((in_array($candidate, $currentValues, true) || in_array($candidate, $newValues, true)) && !in_array($candidate, $merged, true)) {
				$merged[] = $candidate;
			}
		}

		foreach ([$currentValues, $newValues] as $values) {
			foreach ($values as $candidate) {
				if (!in_array($candidate, $merged, true)) {
					$merged[] = $candidate;
				}
			}
		}

		return $merged;
	}

	/**
	 * @param list<mixed> $allowedValues
	 * @param list<mixed> $groupDefaultValues
	 */
	private function pickValueChoiceDefault(
		IPolicyDefinition $definition,
		mixed $currentValue,
		array $allowedValues,
		array $groupDefaultValues,
		PolicyContext $context,
	): mixed {
		$normalizedCurrentValue = $definition->normalizeValue($currentValue);
		$defaultValue = $definition->normalizeValue($definition->defaultSystemValue());

		if (count($groupDefaultValues) === 1 && in_array($groupDefaultValues[0], $allowedValues, true)) {
			return $groupDefaultValues[0];
		}

		if ($normalizedCurrentValue !== $defaultValue && in_array($normalizedCurrentValue, $allowedValues, true)) {
			return $normalizedCurrentValue;
		}

		$orderedAllowedValues = $this->mergeUnionAllowedValues($definition->allowedValues($context), [], $allowedValues);
		return $orderedAllowedValues[0] ?? $normalizedCurrentValue;
	}
}
