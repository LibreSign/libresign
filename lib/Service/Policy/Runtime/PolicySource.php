<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Runtime;

use OCA\Libresign\Db\PermissionSet;
use OCA\Libresign\Db\PermissionSetBinding;
use OCA\Libresign\Db\PermissionSetBindingMapper;
use OCA\Libresign\Db\PermissionSetMapper;
use OCA\Libresign\Service\Policy\Contract\IPolicySource;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCP\AppFramework\Services\IAppConfig;

class PolicySource implements IPolicySource {
	public function __construct(
		private IAppConfig $appConfig,
		private PermissionSetMapper $permissionSetMapper,
		private PermissionSetBindingMapper $bindingMapper,
		private PolicyRegistry $registry,
	) {
	}

	#[\Override]
	public function loadSystemPolicy(string $policyKey): ?PolicyLayer {
		$definition = $this->registry->get($policyKey);
		$defaultValue = $definition->normalizeValue($definition->defaultSystemValue());
		$value = $this->appConfig->getAppValueString($definition->getAppConfigKey(), (string)$defaultValue);
		$value = $definition->normalizeValue($value);

		$layer = (new PolicyLayer())
			->setScope('system')
			->setValue($value)
			->setVisibleToChild(true);

		if ($value === $defaultValue) {
			return $layer->setAllowChildOverride(true);
		}

		return $layer
			->setAllowChildOverride(false)
			->setAllowedValues([$value]);
	}

	#[\Override]
	public function loadGroupPolicies(string $policyKey, PolicyContext $context): array {
		$groupIds = $this->resolveGroupIds($context);
		if ($groupIds === []) {
			return [];
		}

		$bindingsByTargetId = [];
		foreach ($this->bindingMapper->findByTargets('group', $groupIds) as $binding) {
			$bindingsByTargetId[$binding->getTargetId()] = $binding;
		}

		$permissionSetIds = [];
		foreach ($bindingsByTargetId as $binding) {
			$permissionSetIds[] = $binding->getPermissionSetId();
		}

		$permissionSetsById = [];
		foreach ($this->permissionSetMapper->findByIds(array_values(array_unique($permissionSetIds))) as $permissionSet) {
			$permissionSetsById[$permissionSet->getId()] = $permissionSet;
		}

		$layers = [];

		foreach ($groupIds as $groupId) {
			$binding = $bindingsByTargetId[$groupId] ?? null;
			if (!$binding instanceof PermissionSetBinding) {
				continue;
			}

			$permissionSet = $permissionSetsById[$binding->getPermissionSetId()] ?? null;
			if (!$permissionSet instanceof PermissionSet) {
				continue;
			}

			$policyConfig = $permissionSet->getPolicyJson()[$policyKey] ?? null;
			if (!is_array($policyConfig)) {
				continue;
			}

			$layers[] = (new PolicyLayer())
				->setScope('group')
				->setValue($policyConfig['defaultValue'] ?? null)
				->setAllowChildOverride((bool)($policyConfig['allowChildOverride'] ?? false))
				->setVisibleToChild((bool)($policyConfig['visibleToChild'] ?? true))
				->setAllowedValues(is_array($policyConfig['allowedValues'] ?? null) ? $policyConfig['allowedValues'] : []);
		}

		return $layers;
	}

	#[\Override]
	public function loadCirclePolicies(string $policyKey, PolicyContext $context): array {
		return [];
	}

	#[\Override]
	public function loadUserPreference(string $policyKey, PolicyContext $context): ?PolicyLayer {
		$userId = $context->getUserId();
		if ($userId === null || $userId === '') {
			return null;
		}

		$definition = $this->registry->get($policyKey);
		$value = $this->appConfig->getUserValue($userId, $definition->getUserPreferenceKey(), '');
		if ($value === '') {
			return null;
		}

		return (new PolicyLayer())
			->setScope('user')
			->setValue($definition->normalizeValue($value));
	}

	#[\Override]
	public function loadRequestOverride(string $policyKey, PolicyContext $context): ?PolicyLayer {
		$requestOverrides = $context->getRequestOverrides();
		if (!array_key_exists($policyKey, $requestOverrides)) {
			return null;
		}

		$definition = $this->registry->get($policyKey);

		return (new PolicyLayer())
			->setScope('request')
			->setValue($definition->normalizeValue($requestOverrides[$policyKey]));
	}

	#[\Override]
	public function clearUserPreference(string $policyKey, PolicyContext $context): void {
		$userId = $context->getUserId();
		if ($userId === null || $userId === '') {
			return;
		}

		$definition = $this->registry->get($policyKey);
		$this->appConfig->deleteUserValue($userId, $definition->getUserPreferenceKey());
	}

	/** @return list<string> */
	private function resolveGroupIds(PolicyContext $context): array {
		$activeContext = $context->getActiveContext();
		if (($activeContext['type'] ?? null) === 'group' && is_string($activeContext['id'] ?? null)) {
			return [$activeContext['id']];
		}

		return $context->getGroups();
	}
}
