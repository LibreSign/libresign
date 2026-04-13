<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\FilePolicy\Contract;

use OCA\Libresign\Db\File as FileEntity;

interface IFilePolicyApplier {
	/** @param array<string, mixed> $data */
	public function apply(FileEntity $file, array $data): void;

	/** @param array<string, mixed> $data */
	public function sync(FileEntity $file, array $data): void;

	/**
	 * Core flow sync is used on the UUID update path where only core flow policies
	 * should trigger recomputation.
	 */
	public function supportsCoreFlowSync(): bool;
}
