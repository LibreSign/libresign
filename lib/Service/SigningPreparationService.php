<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\File\SettingsLoader;
use OCA\Libresign\Service\SignerGeolocation\SignerGeolocationMetadataValidator;
use OCA\Libresign\Service\SignerIpGeolocation\SignerIpGeolocationPolicyService;
use OCA\Libresign\Service\Validation\IdentityDocumentValidator;
use OCA\Libresign\Service\Validation\SignerValidator;
use OCA\Libresign\Service\Validation\VisibleElementValidator;
use OCP\IRequest;
use OCP\IUser;

/**
 * Shared security and policy preflight for interactive and direct signing.
 *
 * No controller or caller may skip the identity, runtime, geolocation or
 * visible-element checks when preparing a signature.
 */
class SigningPreparationService {
	public function __construct(
		private IRequest $request,
		private IdentityDocumentValidator $identityDocumentValidator,
		private SettingsLoader $settingsLoader,
		private VisibleElementValidator $visibleElementValidator,
		private SignerValidator $signerValidator,
		private IdentifyMethodService $identifyMethodService,
		private RequestMetadataService $requestMetadataService,
		private SignerGeolocationMetadataValidator $signerGeolocationMetadataValidator,
		private SignerIpGeolocationPolicyService $signerIpGeolocationPolicyService,
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
		$metadata = $this->requestMetadataService->collectMetadata();

		$normalizedDeviceGeolocation = $this->signerGeolocationMetadataValidator->normalize(
			$deviceGeolocation === [] ? null : $deviceGeolocation,
		);
		$this->signerGeolocationMetadataValidator->validateSubmission($signRequest, $normalizedDeviceGeolocation);
		if ($normalizedDeviceGeolocation !== null) {
			$metadata = $this->signerGeolocationMetadataValidator->mergeDeviceIntoMetadata(
				$metadata,
				$normalizedDeviceGeolocation,
			);
		}

		// Capture the actual signer's IP before an async worker takes over.
		$ipGeolocation = $this->signerIpGeolocationPolicyService->collectMetadata($file, $this->request);
		if ($ipGeolocation !== null) {
			$metadata = $this->signerIpGeolocationPolicyService->mergeIntoMetadata($metadata, $ipGeolocation);
		}

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
