<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Events;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\Files\File;
use OCP\IUserManager;

class SignedEventFactory {
	public function __construct(
		private IUserManager $userManager,
		private IdentifyMethodService $identifyMethodService,
	) {
	}

	public function make(SignRequest $signRequest, FileEntity $libreSignFile, File $signedFile): SignedEvent {
		return new SignedEvent(
			$signRequest,
			$libreSignFile,
			$this->identifyMethodService->getIdentifiedMethod($signRequest->getId()),
			$this->userManager->get($libreSignFile->getUserId()),
			$signedFile,
		);
	}
}
