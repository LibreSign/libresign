<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCP\Accounts\IAccountManager;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;

class AccountSettingsProvider {
	public function __construct(
		private IAccountManager $accountManager,
		private IAppConfig $appConfig,
		private IGroupManager $groupManager,
		private Pkcs12Handler $pkcs12Handler,
	) {
	}

	public function getSettings(?IUser $user = null): array {
		$return['canRequestSign'] = $this->canRequestSign($user);
		$return['hasSignatureFile'] = $this->hasSignatureFile($user);
		$return['isApprover'] = $this->isApprover($user);
		return $return;
	}

	public function getPhoneNumber(IUser $user): string {
		$userAccount = $this->accountManager->getAccount($user);
		return $userAccount->getProperty(IAccountManager::PROPERTY_PHONE)->getValue();
	}

	private function canRequestSign(?IUser $user = null): bool {
		if (!$user) {
			return false;
		}
		$authorized = $this->appConfig->getValueArray(Application::APP_ID, 'approval_group', ['admin']);
		if (empty($authorized)) {
			return false;
		}
		$userGroups = $this->groupManager->getUserGroupIds($user);
		if (!array_intersect($userGroups, $authorized)) {
			return false;
		}
		return true;
	}

	private function hasSignatureFile(?IUser $user = null): bool {
		if (!$user) {
			return false;
		}
		try {
			$this->pkcs12Handler->getPfxOfCurrentSigner($user->getUID());
			return true;
		} catch (LibresignException) {
			return false;
		}
	}

	private function isApprover(?IUser $user = null): bool {
		if (!$user) {
			return false;
		}
		$approvalGroups = $this->appConfig->getValueArray(Application::APP_ID, 'approval_group', ['admin']);
		if (empty($approvalGroups)) {
			return false;
		}
		$userGroups = $this->groupManager->getUserGroupIds($user);
		return (bool)array_intersect($userGroups, $approvalGroups);
	}
}
