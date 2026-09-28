<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\AccountCertificateService;
use OCA\Libresign\Service\IdDocsPolicyService;
use OCA\Libresign\Service\Policy\PolicyAuthorizationService;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCP\Accounts\IAccountManager;
use OCP\Config\IUserConfig;
use OCP\IGroupManager;
use OCP\IUser;

/** @psalm-import-type LibresignAccountCapabilitySettings from \OCA\Libresign\ResponseDefinitions */
class AccountSettingsProvider {
	public function __construct(
		private IAccountManager $accountManager,
		private IdDocsPolicyService $idDocsPolicyService,
		private AccountCertificateService $accountCertificateService,
		private IUserConfig $userConfig,
		private IGroupManager $groupManager,
		private PolicyAuthorizationService $policyAuthorizationService,
		private IdentityDocumentValidator $identityDocumentValidator,
	) {
	}

	/** @psalm-return LibresignAccountCapabilitySettings */
	public function getSettings(?IUser $user = null): array {
		$canApproveIdDocs = $this->idDocsPolicyService->userCanApproveValidationDocuments($user, false);

		return [
			'canRequestSign' => $canApproveIdDocs,
			'hasSignatureFile' => $this->hasSignatureFile($user),
			'isApprover' => $canApproveIdDocs,
		];
	}

	public function getPhoneNumber(?IUser $user): string {
		if (!$user) {
			return '';
		}
		$userAccount = $this->accountManager->getAccount($user);
		return $userAccount->getProperty(IAccountManager::PROPERTY_PHONE)->getValue();
	}

	public function hasSignatureFile(?IUser $user = null): bool {
		return $this->accountCertificateService->hasSignatureFile($user);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getConfig(?IUser $user = null): array {
		$info['identificationDocumentsFlow'] = $this->idDocsPolicyService->isIdentificationDocumentsEnabled($user);
		$info['hasSignatureFile'] = $this->hasSignatureFile($user);
		$info['phoneNumber'] = $this->getPhoneNumber($user);
		$info['isApprover'] = $this->identityDocumentValidator->userCanApproveValidationDocuments($user, false);
		$info['id_docs_filters'] = $this->getUserConfigIdDocsFilters($user);
		$info['id_docs_sort'] = $this->getUserConfigIdDocsSort($user);
		$info['crl_filters'] = $this->getUserConfigCrlFilters($user);
		$info['crl_sort'] = $this->getUserConfigCrlSort($user);
		$info['files_list_grid_view'] = $this->getUserConfigByKey('files_list_grid_view', $user) === '1';
		$info['files_list_signer_identify_tab'] = $this->getUserConfigByKey('files_list_signer_identify_tab', $user);
		$info['files_list_sorting_mode'] = $this->getUserConfigByKey('files_list_sorting_mode', $user) ?: 'name';
		$info['files_list_sorting_direction'] = $this->getUserConfigByKey('files_list_sorting_direction', $user) ?: 'asc';
		$info['policy_workbench_catalog_compact_view'] = $this->getUserConfigByKey('policy_workbench_catalog_compact_view', $user) === '1';
		$info['policy_workbench_catalog_collapsed'] = $this->getUserConfigByKey('policy_workbench_catalog_collapsed', $user) === '1';
		$info['policy_workbench_category_collapsed_state'] = $this->getUserConfigJsonByKey('policy_workbench_category_collapsed_state', $user);
		$info['warn_without_visible_signature_fields'] = $this->getUserConfigByKey('warn_without_visible_signature_fields', $user) !== '0';
		$info['can_manage_group_policies'] = $this->policyAuthorizationService->canUserManageGroupPolicies($user);
		$info['manageable_policy_group_ids'] = $this->policyAuthorizationService->getManageablePolicyGroupIds($user);

		return array_filter($info, static fn (mixed $value): bool => $value !== null && $value !== '');
	}

	public function getConfigFilters(?IUser $user = null): array {
		$info['files_list_filter_modified'] = $this->getUserConfigByKey('files_list_filter_modified', $user);
		$info['files_list_filter_status'] = $this->getUserConfigByKey('files_list_filter_status', $user);

		return $info;
	}

	public function getConfigSorting(?IUser $user = null): array {
		$info['files_list_sorting_mode'] = $this->getUserConfigByKey('files_list_sorting_mode', $user) ?: 'name';
		$info['files_list_sorting_direction'] = $this->getUserConfigByKey('files_list_sorting_direction', $user) ?: 'asc';

		return $info;
	}

	private function getUserConfigByKey(string $key, ?IUser $user = null): string {
		if (!$user) {
			return '';
		}
		return $this->userConfig->getValueString($user->getUID(), Application::APP_ID, $key);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function getUserConfigJsonByKey(string $key, ?IUser $user = null): ?array {
		if (!$user) {
			return null;
		}

		$value = $this->userConfig->getValueString($user->getUID(), Application::APP_ID, $key, '');
		if (empty($value)) {
			return null;
		}

		$decoded = json_decode($value, true);
		return is_array($decoded) ? $decoded : null;
	}

	private function getUserConfigIdDocsFilters(?IUser $user = null): array {
		if (!$user) {
			return [];
		}

		$value = $this->userConfig->getValueString($user->getUID(), Application::APP_ID, 'id_docs_filters', '');
		if (empty($value)) {
			return [];
		}

		$decoded = json_decode($value, true);
		return is_array($decoded) ? $decoded : [];
	}

	private function getUserConfigCrlFilters(?IUser $user = null): array {
		if (!$user || !$this->groupManager->isAdmin($user->getUID())) {
			return [];
		}

		$value = $this->userConfig->getValueString($user->getUID(), Application::APP_ID, 'crl_filters', '');
		if (empty($value)) {
			return [];
		}

		$decoded = json_decode($value, true);
		return is_array($decoded) ? $decoded : [];
	}

	private function getUserConfigCrlSort(?IUser $user): array {
		if (!$user || !$this->groupManager->isAdmin($user->getUID())) {
			return ['sortBy' => 'revoked_at', 'sortOrder' => 'DESC'];
		}

		$value = $this->userConfig->getValueString($user->getUID(), Application::APP_ID, 'crl_sort', '');
		if (empty($value)) {
			return ['sortBy' => 'revoked_at', 'sortOrder' => 'DESC'];
		}

		$decoded = json_decode($value, true);
		return is_array($decoded) ? $decoded : ['sortBy' => 'revoked_at', 'sortOrder' => 'DESC'];
	}

	private function getUserConfigIdDocsSort(?IUser $user): array {
		if (!$user || !$this->identityDocumentValidator->userCanApproveValidationDocuments($user, false)) {
			return ['sortBy' => null, 'sortOrder' => null];
		}

		$value = $this->userConfig->getValueString($user->getUID(), Application::APP_ID, 'id_docs_sort', '');
		if (empty($value)) {
			return ['sortBy' => null, 'sortOrder' => null];
		}

		$decoded = json_decode($value, true);
		return is_array($decoded) ? $decoded : ['sortBy' => null, 'sortOrder' => null];
	}
}
