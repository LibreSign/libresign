<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\ExperimentalFeaturesService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Validation\SignerValidator;
use OCA\Libresign\Service\Validation\SigningRequestValidator;
use OCP\IUser;
use OCP\IUserSession;

/**
 * Signer authorization boundary for experimental external signing.
 *
 * A guest's invitation UUID is NOT evidence of signer consent. Guests must
 * prove a specific bound identification method with their signing token;
 * authenticated account signers must match the bound Nextcloud UID. Both
 * paths use the existing LibreSign signing policy and identity validators.
 *
 * This authorizes an operation in LibreSign, NOT a private key at the CSC
 * provider. The CSC adapter must separately prove operation-bound SAD;
 * production personal signing remains disabled until consent is demonstrated.
 *
 * Call again immediately before sensitive discovery, submission, status
 * recovery or publication. API endpoints must not accept an actorId from
 * client input and should map denied access to a nondisclosing response.
 */
final class ExternalSigningAuthorizationService {
	public function __construct(
		private ExperimentalFeaturesService $experimentalFeatures,
		private SignRequestMapper $signRequests,
		private FileMapper $files,
		private SignerValidator $signerValidator,
		private SigningRequestValidator $signingRequestValidator,
		private IdentifyMethodService $identificationMethods,
		private IUserSession $userSession,
	) {
	}

	public function authorize(
		string $signRequestUuid,
		string $identificationMethod,
		string $identifyValue,
		#[\SensitiveParameter] string $signingToken = '',
	): AuthorizedExternalSigningRequest {
		if (!$this->experimentalFeatures->isEnabled()) {
			throw new \DomainException('Experimental external signing is disabled');
		}
		if (strlen($signRequestUuid) > 64 || strlen($identificationMethod) > 64
			|| strlen($identifyValue) > 255 || strlen($signingToken) > 16384
			|| $identificationMethod === '' || $identifyValue === '') {
			throw new \InvalidArgumentException('Invalid external signing authorization context');
		}
		$currentUser = $this->userSession->getUser();
		$this->signerValidator->validateSigner($signRequestUuid, $currentUser);
		$signRequest = $this->signRequests->getByUuid($signRequestUuid);
		$signRequestId = $signRequest->getId();
		$fileId = $signRequest->getFileId();
		if ($signRequestId < 1 || $fileId === null || $fileId < 1 || $signRequest->hasCompletedSigning()
			|| !$signRequest->getParticipantRoleEnum()->canSign()) {
			throw new \DomainException('External signing request is unavailable');
		}
		$file = $this->files->getById($fileId);
		$this->signingRequestValidator->fileCanBeSigned($file);

		// Do not rely on SignerValidator's fallback from a requested method
		// to the first available factor. For external credential discovery,
		// the method AND its exact bound identity must match independently.
		$matched = false;
		$methods = $this->identificationMethods->getIdentifyMethodsFromSignRequestId($signRequestId);
		foreach ($methods[$identificationMethod] ?? [] as $method) {
			$entity = $method->getEntity();
			if (hash_equals($identifyValue, $entity->getIdentifierValue())) {
				$matched = true;
				break;
			}
		}
		if (!$matched) {
			throw new \DomainException('External signing identity is not authorized');
		}
		if ($identificationMethod === IdentifyMethodService::IDENTIFY_ACCOUNT) {
			if (!$currentUser instanceof IUser || !hash_equals($currentUser->getUID(), $identifyValue)) {
				throw new \DomainException('External signing account identity is not authorized');
			}
		} elseif ($identificationMethod === IdentifyMethodService::IDENTIFY_CLICK_TO_SIGN || $signingToken === '') {
			// A link-only or Click to Sign flow is insufficient proof for
			// revealing remote credentials to a guest or another signer.
			throw new \DomainException('External signing requires verified signer authorization');
		}

		$this->signerValidator->validateCredentials(
			$signRequest, $identificationMethod, $identifyValue, $signingToken,
		);
		// The ID represents the signing participant, not their login method.
		// A guest who later logs in must not create another session for the
		// same SignRequest. The database enforces this independently.
		return new AuthorizedExternalSigningRequest($file, $signRequest, 'sign-request:' . $signRequestId);
	}
}
