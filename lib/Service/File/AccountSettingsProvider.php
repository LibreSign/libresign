<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Service\IdDocsPolicyService;
use OCP\Accounts\IAccountManager;
use OCP\IUser;

class AccountSettingsProvider {
	public function __construct(
		private IAccountManager $accountManager,
		private IdDocsPolicyService $idDocsPolicyService,
		private Pkcs12Handler $pkcs12Handler,
	) {
	}

	public function getSettings(?IUser $user = null): array {
		$canApproveIdDocs = $this->idDocsPolicyService->userCanApproveValidationDocuments($user, false);
		$return['canRequestSign'] = $canApproveIdDocs;
		$return['hasSignatureFile'] = $this->hasSignatureFile($user);
		$return['isApprover'] = $canApproveIdDocs;
		return $return;
	}

	public function getPhoneNumber(IUser $user): string {
		$userAccount = $this->accountManager->getAccount($user);
		return $userAccount->getProperty(IAccountManager::PROPERTY_PHONE)->getValue();
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
}
