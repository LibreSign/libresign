<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Service\Signing\External\AuthorizedRequest;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\ExternalSigningAccessDeniedException;
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
	): AuthorizedRequest {
		$this->assertValidContext($signRequestUuid, $identificationMethod, $identifyValue, $signingToken);
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
		$this->assertBoundIdentity($signRequestId, $identificationMethod, $identifyValue);
		$this->assertVerifiedIdentity($currentUser, $identificationMethod, $identifyValue, $signingToken);
		try {
			$this->signerValidator->validateCredentials(
				$signRequest, $identificationMethod, $identifyValue, $signingToken,
			);
		} catch (\DomainException $e) {
			throw new ExternalSigningAccessDeniedException('External signing authorization denied', 0, $e);
		}

		return new AuthorizedRequest($file, $signRequest, 'sign-request:' . $signRequestId);
	}

	private function assertValidContext(string $uuid, string $method, string $identity, string $token): void {
		if (!$this->experimentalFeatures->isEnabled()) {
			throw new \DomainException('Experimental external signing is disabled');
		}
		if (strlen($uuid) > 64 || strlen($method) > 64 || strlen($identity) > 255
			|| strlen($token) > 16384 || $method === '' || $identity === '') {
			throw new \InvalidArgumentException('Invalid external signing authorization context');
		}
	}

	private function assertBoundIdentity(int $signRequestId, string $method, string $identity): void {
		$methods = $this->identificationMethods->getIdentifyMethodsFromSignRequestId($signRequestId);
		foreach ($methods[$method] ?? [] as $candidate) {
			if (hash_equals($identity, $candidate->getEntity()->getIdentifierValue())) {
				return;
			}
		}
		throw new ExternalSigningAccessDeniedException('External signing identity is not authorized');
	}

	private function assertVerifiedIdentity(?IUser $user, string $method, string $identity, string $token): void {
		if ($method === IdentifyMethodService::IDENTIFY_ACCOUNT) {
			if (!$user instanceof IUser || !hash_equals($user->getUID(), $identity)) {
				throw new ExternalSigningAccessDeniedException('External signing account identity is not authorized');
			}
			return;
		}
		if ($method === IdentifyMethodService::IDENTIFY_CLICK_TO_SIGN || $token === '') {
			throw new ExternalSigningAccessDeniedException('External signing requires verified signer authorization');
		}
	}
}
