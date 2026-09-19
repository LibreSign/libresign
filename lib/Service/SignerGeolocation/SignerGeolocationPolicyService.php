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
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\SignerGeolocationMode;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Policy\Provider\SignerGeolocation\SignerGeolocationPolicy;
use OCA\Libresign\Service\Policy\Provider\SignerGeolocation\SignerGeolocationPolicyValue;
use OCA\Libresign\Service\Policy\ResolvesFrozenFilePolicySnapshot;
use OCP\IL10N;

/**
 * Reads the device geolocation rules that a signature request was created with.
 *
 * The value frozen on the request is the only source of truth for the signing
 * flow: the live policy is never consulted here, so a later policy change cannot
 * alter an existing request, and a request that never stored a snapshot keeps
 * device geolocation disabled.
 */
class SignerGeolocationPolicyService {
	use ResolvesFrozenFilePolicySnapshot;

	public const METADATA_REQUIREMENT_KEY = 'deviceGeolocationRequirement';

	public function __construct(
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private IL10N $l10n,
	) {
	}

	/**
	 * @return array{mode: string}
	 */
	public function getPolicyValue(?FileEntity $file = null): array {
		return $this->findSnapshot($file) ?? SignerGeolocationPolicyValue::defaults();
	}

	public function getFrozenRequirement(SignRequest $signRequest): ?SignerGeolocationMode {
		$metadata = $signRequest->getMetadata() ?? [];
		$stored = $metadata[self::METADATA_REQUIREMENT_KEY] ?? null;
		if (!is_string($stored)) {
			return null;
		}

		$requirement = SignerGeolocationMode::tryFrom($stored);
		if ($requirement === SignerGeolocationMode::DISABLED || $requirement === SignerGeolocationMode::REQUIRED) {
			return $requirement;
		}

		return null;
	}

	public function resolveEffectiveRequirement(
		FileEntity $file,
		bool $requesterRequiresGeolocation,
	): SignerGeolocationMode {
		$policy = $this->getPolicyValue($file);
		$mode = SignerGeolocationMode::from($policy['mode']);

		if ($mode === SignerGeolocationMode::DISABLED) {
			return SignerGeolocationMode::DISABLED;
		}

		if ($mode === SignerGeolocationMode::REQUIRED) {
			return SignerGeolocationMode::REQUIRED;
		}

		return $requesterRequiresGeolocation
			? SignerGeolocationMode::REQUIRED
			: SignerGeolocationMode::DISABLED;
	}

	public function validateRequesterConfiguration(
		FileEntity $file,
		bool $requesterRequiresGeolocation,
	): void {
		if (!$requesterRequiresGeolocation) {
			return;
		}

		$policy = $this->getPolicyValue($file);
		$mode = SignerGeolocationMode::from($policy['mode']);

		if ($mode === SignerGeolocationMode::DISABLED) {
			throw new LibresignException($this->l10n->t('Geolocation is disabled by policy.'));
		}
	}

	public function persistEffectiveRequirement(
		SignRequest $signRequest,
		FileEntity $file,
		bool $requesterRequiresGeolocation,
	): void {
		$this->validateRequesterConfiguration($file, $requesterRequiresGeolocation);
		$effective = $this->resolveEffectiveRequirement($file, $requesterRequiresGeolocation);

		$signRequestId = $signRequest->getId();
		if ($signRequestId === null) {
			throw new \InvalidArgumentException('Sign request must be persisted before storing geolocation requirement');
		}

		$metadata = $signRequest->getMetadata() ?? [];
		$metadata[self::METADATA_REQUIREMENT_KEY] = $effective->value;
		$signRequest->setMetadata($metadata);
		$this->signRequestMapper->update($signRequest);
	}

	protected function getFrozenPolicyKey(): string {
		return SignerGeolocationPolicy::KEY;
	}

	/**
	 * @return array{mode: string}|null
	 */
	protected function normalizeFrozenPolicyEffectiveValue(mixed $value): ?array {
		return SignerGeolocationPolicyValue::normalize($value);
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
