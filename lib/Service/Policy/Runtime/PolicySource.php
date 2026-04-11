<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Runtime;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\PermissionSet;
use OCA\Libresign\Db\PermissionSetBinding;
use OCA\Libresign\Db\PermissionSetBindingMapper;
use OCA\Libresign\Db\PermissionSetMapper;
use OCA\Libresign\Service\Policy\Contract\IPolicySource;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Provider\PolicyProviders;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Services\IAppConfig;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class PolicySource implements IPolicySource {
	public function __construct(
		private IAppConfig $appConfig,
		private PermissionSetMapper $permissionSetMapper,
		private PermissionSetBindingMapper $bindingMapper,
		private PolicyRegistry $registry,
		private IDBConnection $db,
	) {
	}

	#[\Override]
	public function loadSystemPolicy(string $policyKey): ?PolicyLayer {
		$definition = $this->registry->get($policyKey);
		$defaultValue = $definition->normalizeValue($definition->defaultSystemValue());
		$hasExplicitSystemValue = $this->appConfig->hasAppKey($definition->getAppConfigKey());
		$storedValue = $hasExplicitSystemValue
			? $this->readSystemValue($definition->getAppConfigKey(), $defaultValue)
			: null;
		$value = $hasExplicitSystemValue
			? $definition->normalizeValue($storedValue)
			: $defaultValue;

		$layer = (new PolicyLayer())
			->setScope($hasExplicitSystemValue ? 'global' : 'system')
			->setValue($value)
			->setVisibleToChild(true);

		if (!$hasExplicitSystemValue) {
			return $layer->setAllowChildOverride(true);
		}

		if ($value === $defaultValue) {
			$allowChildOverride = $this->appConfig->getAppValueString(
				$this->getSystemAllowOverrideConfigKey($definition->getAppConfigKey()),
				'0',
			) === '1';

			if ($allowChildOverride) {
				// Explicitly persisted default value ("let users choose")
				return $layer
					->setAllowChildOverride(true)
					->setAllowedValues([]);
			}

			return $layer->setAllowChildOverride(true);
		}

		$allowChildOverride = $this->appConfig->getAppValueString(
			$this->getSystemAllowOverrideConfigKey($definition->getAppConfigKey()),
			'0',
		) === '1';

		return $layer
			->setAllowChildOverride($allowChildOverride)
			->setAllowedValues($allowChildOverride ? [] : [$value]);
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

			$policyConfig = $permissionSet->getDecodedPolicyJson()[$policyKey] ?? null;
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

	/**
	 * @param list<string> $policyKeys
	 * @return array<string, list<PolicyLayer>>
	 */
	#[\Override]
	public function loadAllGroupPolicies(array $policyKeys, PolicyContext $context): array {
		/** @var array<string, list<PolicyLayer>> $result */
		$result = array_fill_keys($policyKeys, []);

		$groupIds = $this->resolveGroupIds($context);
		if ($groupIds === []) {
			return $result;
		}

		$bindingsByTargetId = [];
		foreach ($this->bindingMapper->findByTargets('group', $groupIds) as $binding) {
			$bindingsByTargetId[$binding->getTargetId()] = $binding;
		}

		$permissionSetIds = array_values(array_unique(array_map(
			static fn (PermissionSetBinding $b): int => $b->getPermissionSetId(),
			$bindingsByTargetId,
		)));

		$permissionSetsById = [];
		foreach ($this->permissionSetMapper->findByIds($permissionSetIds) as $permissionSet) {
			$permissionSetsById[$permissionSet->getId()] = $permissionSet;
		}

		foreach ($groupIds as $groupId) {
			$binding = $bindingsByTargetId[$groupId] ?? null;
			if (!$binding instanceof PermissionSetBinding) {
				continue;
			}

			$permissionSet = $permissionSetsById[$binding->getPermissionSetId()] ?? null;
			if (!$permissionSet instanceof PermissionSet) {
				continue;
			}

			$policyJson = $permissionSet->getDecodedPolicyJson();
			foreach ($policyKeys as $policyKey) {
				$policyConfig = $policyJson[$policyKey] ?? null;
				if (!is_array($policyConfig)) {
					continue;
				}

				$result[$policyKey][] = (new PolicyLayer())
					->setScope('group')
					->setValue($policyConfig['defaultValue'] ?? null)
					->setAllowChildOverride((bool)($policyConfig['allowChildOverride'] ?? false))
					->setVisibleToChild((bool)($policyConfig['visibleToChild'] ?? true))
					->setAllowedValues(is_array($policyConfig['allowedValues'] ?? null) ? $policyConfig['allowedValues'] : []);
			}
		}

		return $result;
	}

	/**
	 * @param list<string> $policyKeys
	 * @return array<string, PolicyLayer>
	 */
	#[\Override]
	public function loadAllUserPreferences(array $policyKeys, PolicyContext $context): array {
		$userId = $context->getUserId();
		if ($userId === null || $userId === '') {
			return [];
		}

		$userPreferenceKeyByPolicy = [];
		foreach ($policyKeys as $policyKey) {
			$userPreferenceKeyByPolicy[$policyKey] = $this->registry->get($policyKey)->getUserPreferenceKey();
		}
		$policyKeyByPreferenceKey = array_flip($userPreferenceKeyByPolicy);

		$query = $this->db->getQueryBuilder();
		$query->select('configkey', 'configvalue')
			->from('preferences')
			->where($query->expr()->eq('userid', $query->createNamedParameter($userId)))
			->andWhere($query->expr()->eq('appid', $query->createNamedParameter(Application::APP_ID)))
			->andWhere($query->expr()->in('configkey', $query->createNamedParameter(array_values($userPreferenceKeyByPolicy), IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($query->expr()->neq('configvalue', $query->createNamedParameter('')));

		$result = $query->executeQuery();
		$layers = [];
		try {
			while ($row = $result->fetchAssociative()) {
				$policyKey = $policyKeyByPreferenceKey[$row['configkey']] ?? null;
				if ($policyKey === null) {
					continue;
				}

				$definition = $this->registry->get($policyKey);
				$layers[$policyKey] = (new PolicyLayer())
					->setScope('user')
					->setValue($definition->normalizeValue($row['configvalue']));
			}
		} finally {
			$result->closeCursor();
		}

		return $layers;
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
	public function loadGroupPolicyConfig(string $policyKey, string $groupId): ?PolicyLayer {
		$permissionSet = $this->findPermissionSetByGroupId($groupId);
		if (!$permissionSet instanceof PermissionSet) {
			return null;
		}

		$policyConfig = $permissionSet->getDecodedPolicyJson()[$policyKey] ?? null;
		if (!is_array($policyConfig)) {
			return null;
		}

		return $this->createGroupPolicyLayer($policyConfig);
	}

	/**
	 * @param list<string> $groupIds
	 * @param list<string> $userIds
	 * @return array<string, array{groupCount: int, userCount: int}>
	 */
	public function loadRuleCounts(array $groupIds, array $userIds): array {
		$policyKeys = array_keys(PolicyProviders::BY_KEY);
		/** @var array<string, array{groupCount: int, userCount: int}> $counts */
		$counts = [];
		foreach ($policyKeys as $policyKey) {
			$counts[$policyKey] = [
				'groupCount' => 0,
				'userCount' => 0,
			];
		}

		$groupIds = array_values(array_unique(array_filter($groupIds, static fn (string $groupId): bool => $groupId !== '')));
		if ($groupIds !== []) {
			$groupBindings = $this->bindingMapper->findByTargets('group', $groupIds);
			$permissionSetIds = array_values(array_unique(array_map(
				static fn (PermissionSetBinding $binding): int => $binding->getPermissionSetId(),
				$groupBindings,
			)));

			$permissionSetsById = [];
			foreach ($this->permissionSetMapper->findByIds($permissionSetIds) as $permissionSet) {
				$permissionSetsById[$permissionSet->getId()] = $permissionSet;
			}

			foreach ($groupBindings as $binding) {
				$policyJson = $permissionSetsById[$binding->getPermissionSetId()]?->getDecodedPolicyJson() ?? [];
				foreach ($policyJson as $policyKey => $policyConfig) {
					if (!isset($counts[$policyKey]) || !is_array($policyConfig)) {
						continue;
					}

					if (!array_key_exists('defaultValue', $policyConfig) || $policyConfig['defaultValue'] === null) {
						continue;
					}

					$counts[$policyKey]['groupCount']++;
				}
			}
		}

		$userIds = array_values(array_unique(array_filter($userIds, static fn (string $userId): bool => $userId !== '')));
		if ($userIds === []) {
			return $counts;
		}

		$userPreferenceKeyByPolicy = [];
		foreach ($policyKeys as $policyKey) {
			$userPreferenceKeyByPolicy[$policyKey] = $this->registry->get($policyKey)->getUserPreferenceKey();
		}
		$policyKeyByUserPreference = array_flip($userPreferenceKeyByPolicy);

		$query = $this->db->getQueryBuilder();
		$query->select('configkey')
			->selectAlias($query->func()->count('DISTINCT userid'), 'user_count')
			->from('preferences')
			->where($query->expr()->eq('appid', $query->createNamedParameter(Application::APP_ID)))
			->andWhere($query->expr()->in('userid', $query->createNamedParameter($userIds, IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($query->expr()->in('configkey', $query->createNamedParameter(array_values($userPreferenceKeyByPolicy), IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($query->expr()->neq('configvalue', $query->createNamedParameter('')))
			->groupBy('configkey');

		$result = $query->executeQuery();
		try {
			while ($row = $result->fetchAssociative()) {
				$policyKey = $policyKeyByUserPreference[$row['configkey']] ?? null;
				if (!is_string($policyKey) || !isset($counts[$policyKey])) {
					continue;
				}

				$counts[$policyKey]['userCount'] = (int)($row['user_count'] ?? 0);
			}
		} finally {
			$result->closeCursor();
		}

		return $counts;
	}

	/**
	 * Count group/user rules for ALL known targets (no ID filter). Suitable for system admins.
	 *
	 * @return array<string, array{groupCount: int, userCount: int}>
	 */
	public function loadAllRuleCounts(): array {
		$policyKeys = array_keys(PolicyProviders::BY_KEY);
		/** @var array<string, array{groupCount: int, userCount: int}> $counts */
		$counts = [];
		foreach ($policyKeys as $policyKey) {
			$counts[$policyKey] = ['groupCount' => 0, 'userCount' => 0];
		}

		$groupBindings = $this->bindingMapper->findByTargetType('group');
		if ($groupBindings !== []) {
			$permissionSetIds = array_values(array_unique(array_map(
				static fn (PermissionSetBinding $binding): int => $binding->getPermissionSetId(),
				$groupBindings,
			)));

			$permissionSetsById = [];
			foreach ($this->permissionSetMapper->findByIds($permissionSetIds) as $permissionSet) {
				$permissionSetsById[$permissionSet->getId()] = $permissionSet;
			}

			foreach ($groupBindings as $binding) {
				$policyJson = $permissionSetsById[$binding->getPermissionSetId()]?->getDecodedPolicyJson() ?? [];
				foreach ($policyJson as $policyKey => $policyConfig) {
					if (!isset($counts[$policyKey]) || !is_array($policyConfig)) {
						continue;
					}

					if (!array_key_exists('defaultValue', $policyConfig) || $policyConfig['defaultValue'] === null) {
						continue;
					}

					$counts[$policyKey]['groupCount']++;
				}
			}
		}

		$userPreferenceKeyByPolicy = [];
		foreach ($policyKeys as $policyKey) {
			$userPreferenceKeyByPolicy[$policyKey] = $this->registry->get($policyKey)->getUserPreferenceKey();
		}
		$policyKeyByUserPreference = array_flip($userPreferenceKeyByPolicy);

		$query = $this->db->getQueryBuilder();
		$query->select('configkey')
			->selectAlias($query->func()->count('DISTINCT userid'), 'user_count')
			->from('preferences')
			->where($query->expr()->eq('appid', $query->createNamedParameter(Application::APP_ID)))
			->andWhere($query->expr()->in('configkey', $query->createNamedParameter(array_values($userPreferenceKeyByPolicy), IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($query->expr()->neq('configvalue', $query->createNamedParameter('')))
			->groupBy('configkey');

		$result = $query->executeQuery();
		try {
			while ($row = $result->fetchAssociative()) {
				$policyKey = $policyKeyByUserPreference[$row['configkey']] ?? null;
				if (!is_string($policyKey) || !isset($counts[$policyKey])) {
					continue;
				}

				$counts[$policyKey]['userCount'] = (int)($row['user_count'] ?? 0);
			}
		} finally {
			$result->closeCursor();
		}

		return $counts;
	}

	#[\Override]
	public function saveSystemPolicy(string $policyKey, mixed $value, bool $allowChildOverride = false): void {
		$definition = $this->registry->get($policyKey);
		$normalizedValue = $definition->normalizeValue($value);
		$defaultValue = $definition->normalizeValue($definition->defaultSystemValue());
		$allowOverrideConfigKey = $this->getSystemAllowOverrideConfigKey($definition->getAppConfigKey());

		if ($normalizedValue === $defaultValue) {
			if ($allowChildOverride) {
				$this->writeSystemValue($definition->getAppConfigKey(), $normalizedValue);
				$this->appConfig->setAppValueString($allowOverrideConfigKey, '1');
				return;
			}

			$this->appConfig->deleteAppValue($definition->getAppConfigKey());
			$this->appConfig->deleteAppValue($allowOverrideConfigKey);
			return;
		}

		$this->writeSystemValue($definition->getAppConfigKey(), $normalizedValue);
		$this->appConfig->setAppValueString($allowOverrideConfigKey, $allowChildOverride ? '1' : '0');
	}

	private function readSystemValue(string $key, mixed $defaultValue): mixed {
		if (is_int($defaultValue)) {
			return $this->appConfig->getAppValueInt($key, $defaultValue);
		}

		if (is_bool($defaultValue)) {
			return $this->appConfig->getAppValueBool($key, $defaultValue);
		}

		if (is_float($defaultValue)) {
			return $this->appConfig->getAppValueFloat($key, $defaultValue);
		}

		if (is_array($defaultValue)) {
			return $this->appConfig->getAppValueArray($key, $defaultValue);
		}

		return $this->appConfig->getAppValueString($key, (string)$defaultValue);
	}

	private function writeSystemValue(string $key, mixed $value): void {
		if (is_int($value)) {
			$this->appConfig->setAppValueInt($key, $value);
			return;
		}

		if (is_bool($value)) {
			$this->appConfig->setAppValueBool($key, $value);
			return;
		}

		if (is_float($value)) {
			$this->appConfig->setAppValueFloat($key, $value);
			return;
		}

		if (is_array($value)) {
			$this->appConfig->setAppValueArray($key, $value);
			return;
		}

		$this->appConfig->setAppValueString($key, (string)$value);
	}

	private function getSystemAllowOverrideConfigKey(string $policyConfigKey): string {
		return $policyConfigKey . '.allow_child_override';
	}

	#[\Override]
	public function saveGroupPolicy(string $policyKey, string $groupId, mixed $value, bool $allowChildOverride): void {
		$definition = $this->registry->get($policyKey);
		$normalizedValue = $definition->normalizeValue($value);
		$permissionSet = $this->findPermissionSetByGroupId($groupId);
		$now = new \DateTime('now', new \DateTimeZone('UTC'));

		if (!$permissionSet instanceof PermissionSet) {
			$permissionSet = new PermissionSet();
			$permissionSet->setName('group:' . $groupId);
			$permissionSet->setScopeType('group');
			$permissionSet->setCreatedAt($now);
		}

		$policyJson = $permissionSet->getDecodedPolicyJson();
		$policyJson[$policyKey] = [
			'defaultValue' => $normalizedValue,
			'allowChildOverride' => $allowChildOverride,
			'visibleToChild' => true,
			'allowedValues' => $allowChildOverride ? [] : [$normalizedValue],
		];

		$permissionSet->setPolicyJson($policyJson);
		$permissionSet->setUpdatedAt($now);

		if ($permissionSet->getId() > 0) {
			$this->permissionSetMapper->update($permissionSet);
			return;
		}

		/** @var PermissionSet $permissionSet */
		$permissionSet = $this->permissionSetMapper->insert($permissionSet);

		$binding = new PermissionSetBinding();
		$binding->setPermissionSetId($permissionSet->getId());
		$binding->setTargetType('group');
		$binding->setTargetId($groupId);
		$binding->setCreatedAt($now);

		$this->bindingMapper->insert($binding);
	}

	#[\Override]
	public function clearGroupPolicy(string $policyKey, string $groupId): void {
		$binding = $this->findBindingByGroupId($groupId);
		if (!$binding instanceof PermissionSetBinding) {
			return;
		}

		$permissionSet = $this->findPermissionSetByBinding($binding);
		if (!$permissionSet instanceof PermissionSet) {
			return;
		}

		$policyJson = $permissionSet->getDecodedPolicyJson();
		unset($policyJson[$policyKey]);

		if ($policyJson === []) {
			$this->bindingMapper->delete($binding);
			$this->permissionSetMapper->delete($permissionSet);
			return;
		}

		$permissionSet->setPolicyJson($policyJson);
		$permissionSet->setUpdatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$this->permissionSetMapper->update($permissionSet);
	}

	#[\Override]
	public function saveUserPreference(string $policyKey, PolicyContext $context, mixed $value): void {
		$userId = $context->getUserId();
		if ($userId === null || $userId === '') {
			throw new \InvalidArgumentException('A signed-in user is required to save a policy preference.');
		}

		$definition = $this->registry->get($policyKey);
		$normalizedValue = $definition->normalizeValue($value);
		$this->appConfig->setUserValue($userId, $definition->getUserPreferenceKey(), (string)$normalizedValue);
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

	/** @param array<string, mixed> $policyConfig */
	private function createGroupPolicyLayer(array $policyConfig): PolicyLayer {
		return (new PolicyLayer())
			->setScope('group')
			->setValue($policyConfig['defaultValue'] ?? null)
			->setAllowChildOverride((bool)($policyConfig['allowChildOverride'] ?? false))
			->setVisibleToChild((bool)($policyConfig['visibleToChild'] ?? true))
			->setAllowedValues(is_array($policyConfig['allowedValues'] ?? null) ? $policyConfig['allowedValues'] : []);
	}

	private function findBindingByGroupId(string $groupId): ?PermissionSetBinding {
		try {
			return $this->bindingMapper->getByTarget('group', $groupId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	private function findPermissionSetByBinding(PermissionSetBinding $binding): ?PermissionSet {
		try {
			return $this->permissionSetMapper->getById($binding->getPermissionSetId());
		} catch (DoesNotExistException) {
			return null;
		}
	}

	private function findPermissionSetByGroupId(string $groupId): ?PermissionSet {
		$binding = $this->findBindingByGroupId($groupId);
		if (!$binding instanceof PermissionSetBinding) {
			return null;
		}

		return $this->findPermissionSetByBinding($binding);
	}
}
