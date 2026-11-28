<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\External;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;

/** Commit the signing state and document pointer as one database transaction. */
interface PublicationTransaction {
	/** @return array{file:FileEntity,activated:list<SignRequest>,previousStatus:int} */
	public function commit(PublicationContext $context): array;
}
