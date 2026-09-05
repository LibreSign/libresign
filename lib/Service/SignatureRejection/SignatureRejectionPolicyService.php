<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureRejection;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\SignatureRejectionCommentMode;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValue;

/**
 * Reads the rejection rules that a signature request was created with.
 *
 * The value frozen on the request is the only source of truth for the signing
 * flow: the live policy is never consulted here, so a later policy change cannot
 * alter an existing request, and a request that never opted in keeps rejection
 * disabled no matter what the policy allows today.
 *
 * @psalm-type SignatureRejectionPolicyShape = array{
 *     enabled: bool,
 *     comment_mode: string,
 *     cancel_workflow: bool,
 *     public_status: bool,
 *     show_comment_on_validation: bool,
 * }
 */
class SignatureRejectionPolicyService {
	public function __construct(
		private FileMapper $fileMapper,
	) {
	}

	/**
	 * @return SignatureRejectionPolicyShape
	 */
	public function getPolicyValue(?FileEntity $file = null): array {
		return $this->findSnapshot($file) ?? SignatureRejectionPolicyValue::defaults();
	}

	public function isEnabled(?FileEntity $file = null): bool {
		return $this->getPolicyValue($file)['enabled'];
	}

	public function getCommentMode(?FileEntity $file = null): SignatureRejectionCommentMode {
		return SignatureRejectionCommentMode::from($this->getPolicyValue($file)['comment_mode']);
	}

	public function cancelsWorkflow(?FileEntity $file = null): bool {
		return $this->getPolicyValue($file)['cancel_workflow'];
	}

	/**
	 * An envelope is created before the file policy appliers run, so a freshly
	 * created envelope carries no value of its own and the one the request was
	 * created with lives on the documents it contains. A requester editing the
	 * envelope later writes the new value on the envelope itself.
	 *
	 * Every document of an envelope therefore answers with the value of the
	 * envelope, so that documents added to an envelope after it was created cannot
	 * end up governed by different rules than the ones already in it.
	 *
	 * @return SignatureRejectionPolicyShape|null
	 */
	private function findSnapshot(?FileEntity $file): ?array {
		if (!$file instanceof FileEntity) {
			return null;
		}

		$ownSnapshot = $this->extractSnapshot($file->getMetadata() ?? []);

		if ($file->isEnvelope()) {
			return $ownSnapshot ?? $this->findSnapshotOnChildren($file);
		}

		if ($file->hasParent()) {
			return $this->findSnapshotOnEnvelope($file) ?? $ownSnapshot;
		}

		return $ownSnapshot;
	}

	/**
	 * The oldest document of the envelope carries the value the request was
	 * created with, so it is the one that answers for the whole envelope.
	 *
	 * @return SignatureRejectionPolicyShape|null
	 */
	private function findSnapshotOnChildren(FileEntity $envelope): ?array {
		$envelopeId = $envelope->getId();
		if ($envelopeId === null) {
			return null;
		}

		$children = $this->fileMapper->getChildrenFiles($envelopeId);
		usort($children, static fn (FileEntity $a, FileEntity $b): int => ($a->getId() ?? 0) <=> ($b->getId() ?? 0));

		foreach ($children as $child) {
			$childSnapshot = $this->extractSnapshot($child->getMetadata() ?? []);
			if ($childSnapshot !== null) {
				return $childSnapshot;
			}
		}

		return null;
	}

	/** @return SignatureRejectionPolicyShape|null */
	private function findSnapshotOnEnvelope(FileEntity $file): ?array {
		try {
			$envelope = $this->fileMapper->getById($file->getParentFileId());
		} catch (\Throwable) {
			return null;
		}

		return $this->findSnapshot($envelope);
	}

	/**
	 * @param array<string, mixed> $fileMetadata
	 * @return SignatureRejectionPolicyShape|null
	 */
	private function extractSnapshot(array $fileMetadata): ?array {
		$policySnapshot = $fileMetadata['policy_snapshot'] ?? null;
		if (!is_array($policySnapshot)) {
			return null;
		}

		$entry = $policySnapshot[SignatureRejectionPolicy::KEY] ?? null;
		if (!is_array($entry) || !array_key_exists('effectiveValue', $entry)) {
			return null;
		}

		return SignatureRejectionPolicyValue::normalize($entry['effectiveValue']);
	}
}
