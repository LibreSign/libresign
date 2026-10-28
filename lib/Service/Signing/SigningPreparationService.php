<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\File\SettingsLoader;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\SignFileService;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCA\Libresign\Service\Validation\SignerValidator;
use OCA\Libresign\Service\Validation\VisibleElementValidator;
use OCP\IUser;

/**
 * Shared security and policy preflight for interactive and direct signing.
 *
 * Enforces signer and document authorization before preparing the certificate.
 */
class SigningPreparationService {
	public function __construct(
		private IdentityDocumentValidator $identityDocumentValidator,
		private SettingsLoader $settingsLoader,
		private VisibleElementValidator $visibleElementValidator,
		private SignerValidator $signerValidator,
		private IdentifyMethodService $identifyMethodService,
		private SigningMetadataProvider $metadataProvider,
		private SignFileService $signFileService,
	) {
	}

	/**
	 * @param array<string, mixed> $elements
	 * @param array<string, mixed> $deviceGeolocation
	 * @return array{userIdentifier: string, metadata: array<string, mixed>}
	 */
	public function prepare(
		File $file,
		SignRequest $signRequest,
		?IUser $user,
		string $method,
		string $identifyValue = '',
		string $token = '',
		array $elements = [],
		array $deviceGeolocation = [],
	): array {
		$this->identityDocumentValidator->canSignWithIdentificationDocumentStatus(
			$user,
			$this->settingsLoader->getIdentificationDocumentsStatus($user, $signRequest)
		);

		$this->visibleElementValidator->validateVisibleElementsRelation($elements, $signRequest, $user);
		$this->signerValidator->validateCredentials($signRequest, $method, $identifyValue, $token);

		$userIdentifier = $this->identifyMethodService->getUserIdentifier($signRequest->getId());
		$metadata = $this->metadataProvider->collect($file, $signRequest, $deviceGeolocation);

		$this->signFileService->prepareForSigning(
			$file,
			$signRequest,
			$user,
			$userIdentifier,
			$signRequest->getDisplayName(),
			$method !== 'password',
			$method === 'password' ? $token : null,
			$method,
		);

		return ['userIdentifier' => $userIdentifier, 'metadata' => $metadata];
	}
}
