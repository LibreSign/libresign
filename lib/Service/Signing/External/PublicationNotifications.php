<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\External;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCP\Files\File as NextcloudFile;

/** Execute non-transactional follow-up actions only after commit succeeds. */
interface PublicationNotifications {
	/** @param list<SignRequest> $activated */
	public function dispatch(
		FileEntity $file,
		SignRequest $request,
		NextcloudFile $output,
		int $previousStatus,
		array $activated,
	): void;
}
