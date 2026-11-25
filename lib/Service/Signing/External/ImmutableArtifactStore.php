<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\External;

use OCP\Files\File as NextcloudFile;
use OCP\IUserSession;

/**
 * Publish an immutable, session-specific artifact. Existing bytes are never
 * overwritten, including when recovering an interrupted database commit.
 */
final class ImmutableArtifactStore {
	public function __construct(
		private IUserSession $userSession,
	) {
	}

	public function ensure(
		NextcloudFile $source,
		string $sessionId,
		int $fileId,
		int $requestId,
		#[\SensitiveParameter] string $content,
		string $hash,
	): NextcloudFile {
		if (preg_match('/^[a-f0-9]{32}$/D', $sessionId) !== 1) {
			throw new \DomainException('Invalid CSC publication session identifier');
		}
		$name = substr(pathinfo($source->getName(), PATHINFO_FILENAME), 0, 80);
		$filename = $name . '_signed_' . $fileId . '_' . $requestId . '_' . substr($sessionId, 0, 12) . '.pdf';
		$folder = $source->getParent();
		if ($folder->nodeExists($filename)) {
			$existing = $folder->get($filename);
			if (!$existing instanceof NextcloudFile || !hash_equals($hash, hash('sha256', $existing->getContent()))) {
				throw new \DomainException('Existing CSC publication artifact does not match the validated PDF');
			}
			return $existing;
		}
		$owner = $source->getOwner();
		$currentUser = $this->userSession->getUser();
		if ($currentUser?->getUID() !== $owner->getUID()) {
			$this->userSession->setVolatileActiveUser($owner);
		}
		try {
			$created = $folder->newFile($filename, $content);
		} finally {
			if ($currentUser?->getUID() !== $owner->getUID()) {
				$this->userSession->setVolatileActiveUser($currentUser);
			}
		}
		if (!hash_equals($hash, hash('sha256', $created->getContent()))) {
			throw new \DomainException('New CSC publication artifact failed integrity verification');
		}
		return $created;
	}
}
