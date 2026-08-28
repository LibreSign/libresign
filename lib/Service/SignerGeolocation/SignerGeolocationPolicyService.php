<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignerGeolocation;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\SignerGeolocationMode;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignerGeolocation\SignerGeolocationPolicy;
use OCA\Libresign\Service\Policy\Provider\SignerGeolocation\SignerGeolocationPolicyValue;
use OCP\IL10N;
use OCP\IUser;

class SignerGeolocationPolicyService {
	public const METADATA_REQUIREMENT_KEY = 'geolocationRequirement';

	public function __construct(
		private PolicyService $policyService,
		private FileMapper $fileMapper,
		private IL10N $l10n,
	) {
	}

	/**
	 * @return array{mode: string, allowRequesterOverride: bool}
	 */
	public function getPolicyValue(?FileEntity $file = null, ?IUser $user = null): array {
		$snapshotValue = $this->getSnapshotValue($file);
		if ($snapshotValue !== null) {
			return $snapshotValue;
		}

		$resolved = $user instanceof IUser
			? $this->policyService->resolveForUser(SignerGeolocationPolicy::KEY, $user)
			: $this->policyService->resolve(SignerGeolocationPolicy::KEY);

		return SignerGeolocationPolicyValue::normalize($resolved->getEffectiveValue());
	}

	public function getFrozenRequirement(SignRequest $signRequest): ?SignerGeolocationMode {
		$metadata = $signRequest->getMetadata() ?? [];
		$stored = $metadata[self::METADATA_REQUIREMENT_KEY] ?? null;
		if (!is_string($stored)) {
			return null;
		}

		return SignerGeolocationMode::tryFrom($stored);
	}

	public function resolveEffectiveRequirement(
		FileEntity $file,
		bool $requesterRequiresGeolocation,
		?IUser $requester = null,
	): SignerGeolocationMode {
		$policy = $this->getPolicyValue($file, $requester);
		$mode = SignerGeolocationMode::from($policy['mode']);

		if ($mode === SignerGeolocationMode::DISABLED) {
			return SignerGeolocationMode::DISABLED;
		}

		if ($mode === SignerGeolocationMode::REQUIRED) {
			return SignerGeolocationMode::REQUIRED;
		}

		if ($policy['allowRequesterOverride'] && $requesterRequiresGeolocation) {
			return SignerGeolocationMode::REQUIRED;
		}

		return SignerGeolocationMode::OPTIONAL;
	}

	public function validateRequesterConfiguration(
		FileEntity $file,
		bool $requesterRequiresGeolocation,
		?IUser $requester = null,
	): void {
		if (!$requesterRequiresGeolocation) {
			return;
		}

		$policy = $this->getPolicyValue($file, $requester);
		$mode = SignerGeolocationMode::from($policy['mode']);

		if ($mode === SignerGeolocationMode::DISABLED) {
			throw new LibresignException($this->l10n->t('Geolocation is disabled by policy.'));
		}

		if ($mode === SignerGeolocationMode::REQUIRED) {
			return;
		}

		if (!$policy['allowRequesterOverride']) {
			throw new LibresignException($this->l10n->t('Requester geolocation configuration is not allowed by policy.'));
		}
	}

	public function persistEffectiveRequirement(
		SignRequest $signRequest,
		FileEntity $file,
		bool $requesterRequiresGeolocation,
		?IUser $requester = null,
	): void {
		$this->validateRequesterConfiguration($file, $requesterRequiresGeolocation, $requester);
		$effective = $this->resolveEffectiveRequirement($file, $requesterRequiresGeolocation, $requester);

		$metadata = $signRequest->getMetadata() ?? [];
		$metadata[self::METADATA_REQUIREMENT_KEY] = $effective->value;
		$signRequest->setMetadata($metadata);
	}

	/** @return array{mode: string, allowRequesterOverride: bool}|null */
	private function getSnapshotValue(?FileEntity $file): ?array {
		if (!$file instanceof FileEntity) {
			return null;
		}

		$metadata = $file->getMetadata() ?? [];
		$policySnapshot = $metadata['policy_snapshot'] ?? null;
		if (!is_array($policySnapshot)) {
			return null;
		}

		$entry = $policySnapshot[SignerGeolocationPolicy::KEY] ?? null;
		if (!is_array($entry) || !array_key_exists('effectiveValue', $entry)) {
			return null;
		}

		return SignerGeolocationPolicyValue::normalize($entry['effectiveValue']);
	}

	public function getFileFromSignRequest(SignRequest $signRequest): ?FileEntity {
		$fileId = $signRequest->getFileId();
		if ($fileId === null) {
			return null;
		}

		try {
			return $this->fileMapper->getById($fileId);
		} catch (\Throwable) {
			return null;
		}
	}
}
