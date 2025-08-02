<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Events;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\Files\File;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;

class SignedEventFactory {
	public function __construct(
		private IUserManager $userManager,
		private IdentifyMethodService $identifyMethodService,
		private IL10N $l10n,
	) {
	}

	public function make(SignRequest $signRequest, FileEntity $libreSignFile, File $signedFile): SignedEvent {
		$identifyMethod = $this->getIdentifyMethod($signRequest);
		$user = $this->getUser($libreSignFile);
		return new SignedEvent(
			$signRequest,
			$libreSignFile,
			$identifyMethod,
			$user,
			$signedFile,
		);
	}

	protected function getIdentifyMethod(SignRequest $signRequest): IIdentifyMethod {
		return $this->identifyMethodService->getIdentifiedMethod($signRequest->getId());
	}

	protected function getUser(FileEntity $libreSignFile): IUser {
		$user = $this->userManager->get($libreSignFile->getUserId());
		if (!$user instanceof IUser) {
			throw new LibresignException($this->l10n->t('User not found.'));
		}
		return $user;
	}
}
