<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\External;

use OCA\Libresign\Db\File as FileEntity;
use OCP\Files\File as NextcloudFile;

/** Validated PDF revision and immutable artifact handed to the database committer. */
final readonly class PublicationContext {
	/** @param array<string, int|string> $session */
	public function __construct(
		public AuthorizedRequest $authorized,
		public array $session,
		public FileEntity $file,
		public NextcloudFile $source,
		public string $sourceRevision,
		#[\SensitiveParameter] public string $sourcePdf,
		public NextcloudFile $output,
		public string $signedHash,
		#[\SensitiveParameter] public string $certificatePem,
		public string $sessionId,
	) {
	}
}
