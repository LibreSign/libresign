<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCA\Libresign\Db\PermissionSetBindingMapper;
use OCA\Libresign\Db\PermissionSetMapper;
use OCA\Libresign\Enum\SignatureFlow;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Services\IAppConfig;

class SignatureFlowPolicySource implements PolicySourceInterface {
	private const USER_PREFERENCE_KEY = 'policy.signature_flow';

	public function __construct(
		private IAppConfig $appConfig,
		private PermissionSetMapper $permissionSetMapper,
		private PermissionSetBindingMapper $bindingMapper,
	) {
	}

	#[\Override]
	public function loadSystemPolicy(string $policyKey): ?PolicyLayer {
		$value = $this->appConfig->getAppValueString($policyKey, SignatureFlow::NONE->value);

		$layer = (new PolicyLayer())
			->setScope('system')
			->setValue($value)
			->setVisibleToChild(true);

		if ($value === SignatureFlow::NONE->value) {
			return $layer->setAllowChildOverride(true);
		}

		return $layer
			->setAllowChildOverride(false)
			->setAllowedValues([$value]);
	}

	#[\Override]
	public function loadGroupPolicies(string $policyKey, PolicyContext $context): array {
		$groupIds = $this->resolveGroupIds($context);
		$layers = [];

		foreach ($groupIds as $groupId) {
			try {
				$binding = $this->bindingMapper->getByTarget('group', $groupId);
				$permissionSet = $this->permissionSetMapper->getById($binding->getPermissionSetId());
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
			} catch (DoesNotExistException) {
				continue;
			}
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

		$value = $this->appConfig->getUserValue($userId, self::USER_PREFERENCE_KEY, '');
		if ($value === '') {
			return null;
		}

		return (new PolicyLayer())
			->setScope('user')
			->setValue($value);
	}

	#[\Override]
	public function loadRequestOverride(string $policyKey, PolicyContext $context): ?PolicyLayer {
		$requestOverrides = $context->getRequestOverrides();
		if (!array_key_exists($policyKey, $requestOverrides)) {
			return null;
		}

		return (new PolicyLayer())
			->setScope('request')
			->setValue($requestOverrides[$policyKey]);
	}

	#[\Override]
	public function clearUserPreference(string $policyKey, PolicyContext $context): void {
		$userId = $context->getUserId();
		if ($userId === null || $userId === '') {
			return;
		}

		$this->appConfig->deleteUserValue($userId, self::USER_PREFERENCE_KEY);
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
