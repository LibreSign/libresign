<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use Closure;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;

/**
 * Shared envelope/child resolution for write-once file policy snapshots.
 *
 * An envelope is created before the file policy appliers run, so a freshly
 * created envelope may carry no value of its own and the one the request was
 * created with lives on the documents it contains. A requester editing the
 * envelope later writes the new value on the envelope itself.
 *
 * Every document of an envelope therefore answers with the value of the
 * envelope, so that documents added to an envelope after it was created cannot
 * end up governed by different rules than the ones already in it.
 *
 * @template T of array<string, mixed>
 */
final class FrozenFilePolicySnapshotResolver {
	/**
	 * @param Closure(mixed): T $normalizeEffectiveValue
	 */
	public function __construct(
		private FileMapper $fileMapper,
		private string $policyKey,
		private Closure $normalizeEffectiveValue,
	) {
	}

	/**
	 * @return T|null
	 */
	public function findSnapshot(?FileEntity $file): ?array {
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
	 * @return T|null
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

	/**
	 * @return T|null
	 */
	private function findSnapshotOnEnvelope(FileEntity $file): ?array {
		$parentId = $file->getParentFileId();
		if ($parentId === null) {
			return null;
		}

		try {
			$envelope = $this->fileMapper->getById($parentId);
		} catch (\Throwable) {
			return null;
		}

		return $this->findSnapshot($envelope);
	}

	/**
	 * @param array<string, mixed> $fileMetadata
	 * @return T|null
	 */
	private function extractSnapshot(array $fileMetadata): ?array {
		$policySnapshot = $fileMetadata['policy_snapshot'] ?? null;
		if (!is_array($policySnapshot)) {
			return null;
		}

		$entry = $policySnapshot[$this->policyKey] ?? null;
		if (!is_array($entry) || !array_key_exists('effectiveValue', $entry)) {
			return null;
		}

		return ($this->normalizeEffectiveValue)($entry['effectiveValue']);
	}
}
