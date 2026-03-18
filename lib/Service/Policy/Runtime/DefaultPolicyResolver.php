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
				$context,
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
				$context,
				$currentValue,
				$currentSourceScope,
				$canOverrideBelow,
				$visible,
			);
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

		if ($layer->getValue() !== null && ($currentSourceScope === 'system' || $canOverrideBelow)) {
			$currentValue = $definition->normalizeValue($layer->getValue());
			$definition->validateValue($currentValue, $context);
			$currentSourceScope = $layer->getScope();
		}

		$canOverrideBelow = $layer->isAllowChildOverride();

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
}
