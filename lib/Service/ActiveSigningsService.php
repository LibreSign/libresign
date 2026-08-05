<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethod as IdentifyMethodEntity;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;

class ActiveSigningsService {
	private const EMAIL_IDENTIFY_METHOD = 'email';

	public function __construct(
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private IdentifyMethodMapper $identifyMethodMapper,
	) {
	}

	/**
	 * @return list<array{
	 *     id: int,
	 *     uuid: string,
	 *     name: string,
	 *     signerEmail: string,
	 *     signerDisplayName: string,
	 *     updatedAt: int,
	 * }>
	 */
	public function getActiveSignings(): array {
		$activeSignings = $this->fileMapper->findByStatus(FileStatus::SIGNING_IN_PROGRESS->value);

		$result = [];
		foreach ($activeSignings as $file) {
			$activeSigner = $this->resolveActiveSigner($file);
			$result[] = [
				'id' => $file->getId(),
				'uuid' => $file->getUuid(),
				'name' => $file->getName(),
				'signerEmail' => $activeSigner['email'],
				'signerDisplayName' => $activeSigner['displayName'],
				'updatedAt' => $this->resolveActiveSigningUpdatedAt($file),
			];
		}

		return $result;
	}

	/**
	 * @return array{email: string, displayName: string}
	 */
	private function resolveActiveSigner(FileEntity $file): array {
		$signRequest = $this->findActiveSignRequest($this->signRequestMapper->getByFileId($file->getId()));
		if (!$signRequest instanceof SignRequestEntity) {
			return [
				'email' => '',
				'displayName' => '',
			];
		}

		$identifiedMethod = $this->findPreferredIdentifyMethod(
			$this->identifyMethodMapper->getIdentifyMethodsFromSignRequestId($signRequest->getId())
		);

		$signerEmail = '';
		if ($identifiedMethod instanceof IdentifyMethodEntity
			&& $identifiedMethod->getIdentifierKey() === self::EMAIL_IDENTIFY_METHOD
		) {
			$signerEmail = $identifiedMethod->getIdentifierValue();
		}

		return [
			'email' => $signerEmail,
			'displayName' => $signRequest->getDisplayName(),
		];
	}

	/**
	 * @param list<SignRequestEntity> $signRequests
	 */
	private function findActiveSignRequest(array $signRequests): ?SignRequestEntity {
		foreach ($signRequests as $signRequest) {
			if ($signRequest->getStatusEnum() === SignRequestStatus::ABLE_TO_SIGN) {
				return $signRequest;
			}
		}

		foreach ($signRequests as $signRequest) {
			if ($signRequest->getSigned() === null) {
				return $signRequest;
			}
		}

		return $signRequests[0] ?? null;
	}

	/**
	 * @param list<IdentifyMethodEntity> $identifyMethods
	 */
	private function findPreferredIdentifyMethod(array $identifyMethods): ?IdentifyMethodEntity {
		$firstMethod = null;

		foreach ($identifyMethods as $identifyMethod) {
			$firstMethod ??= $identifyMethod;

			if ($identifyMethod->getIdentifiedAtDate() !== null) {
				return $identifyMethod;
			}
		}

		return $firstMethod;
	}

	private function resolveActiveSigningUpdatedAt(FileEntity $file): int {
		$metadata = $file->getMetadata();
		if (is_array($metadata) && isset($metadata['status_changed_at']) && is_string($metadata['status_changed_at'])) {
			$statusChangedAt = date_create_immutable($metadata['status_changed_at']);
			if ($statusChangedAt instanceof \DateTimeImmutable) {
				return $statusChangedAt->getTimestamp();
			}
		}

		return $file->getCreatedAt()?->getTimestamp() ?? 0;
	}
}
