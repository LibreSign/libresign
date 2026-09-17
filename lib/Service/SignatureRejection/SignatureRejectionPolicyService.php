<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureRejection;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\SignatureRejectionBehavior;
use OCA\Libresign\Enum\SignatureRejectionCommentMode;
use OCA\Libresign\Enum\SignatureRejectionVisibility;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyConfig;

/**
 * Reads the rejection rules that a signature request was created with.
 *
 * The configuration frozen on the request is the only source of truth for the
 * signing flow: the live policy is never consulted here, so a later policy
 * change cannot alter an existing request, and a request that never recorded a
 * configuration keeps rejection disabled no matter what the policy allows
 * today.
 */
class SignatureRejectionPolicyService {
	public function __construct(
		private FileMapper $fileMapper,
	) {
	}

	public function getConfig(?FileEntity $file = null): SignatureRejectionPolicyConfig {
		$snapshot = $this->findSnapshot($file);

		return $snapshot === null
			? SignatureRejectionPolicyConfig::defaults()
			: SignatureRejectionPolicyConfig::fromKeyedValues($snapshot);
	}

	public function isEnabled(?FileEntity $file = null): bool {
		return $this->getConfig($file)->isEnabled();
	}

	public function getBehavior(?FileEntity $file = null): SignatureRejectionBehavior {
		return $this->getConfig($file)->getBehavior();
	}

	public function getCommentMode(?FileEntity $file = null): SignatureRejectionCommentMode {
		return $this->getConfig($file)->getCommentMode();
	}

	public function cancelsWorkflow(?FileEntity $file = null): bool {
		return $this->getConfig($file)->cancelsWorkflow();
	}

	public function getVisibility(?FileEntity $file = null): SignatureRejectionVisibility {
		return $this->getConfig($file)->getVisibility();
	}

	public function getCommentVisibility(?FileEntity $file = null): SignatureRejectionVisibility {
		return $this->getConfig($file)->getCommentVisibility();
	}

	/**
	 * An envelope is created before the file policy appliers run, so a freshly
	 * created envelope carries no configuration of its own and the one the
	 * request was created with lives on the documents it contains. A requester
	 * editing the envelope later writes the new configuration on the envelope
	 * itself.
	 *
	 * Every document of an envelope therefore answers with the configuration of
	 * the envelope, so that documents added to an envelope after it was created
	 * cannot end up governed by different rules than the ones already in it.
	 *
	 * @return array<string, mixed>|null
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
	 * The oldest document of the envelope carries the configuration the request
	 * was created with, so it is the one that answers for the whole envelope.
	 *
	 * @return array<string, mixed>|null
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

	/** @return array<string, mixed>|null */
	private function findSnapshotOnEnvelope(FileEntity $file): ?array {
		try {
			$envelope = $this->fileMapper->getById($file->getParentFileId());
		} catch (\Throwable) {
			return null;
		}

		return $this->findSnapshot($envelope);
	}

	/**
	 * A request records one snapshot entry per rejection setting. Entries that
	 * are missing keep the default of their setting, so a snapshot written by an
	 * older version of the request still reads cleanly.
	 *
	 * @param array<string, mixed> $fileMetadata
	 * @return array<string, mixed>|null
	 */
	private function extractSnapshot(array $fileMetadata): ?array {
		$policySnapshot = $fileMetadata['policy_snapshot'] ?? null;
		if (!is_array($policySnapshot)) {
			return null;
		}

		$values = [];
		foreach (SignatureRejectionPolicy::ALL_KEYS as $policyKey) {
			$entry = $policySnapshot[$policyKey] ?? null;
			if (is_array($entry) && array_key_exists('effectiveValue', $entry)) {
				$values[$policyKey] = $entry['effectiveValue'];
			}
		}

		return $values === [] ? null : $values;
	}
}
