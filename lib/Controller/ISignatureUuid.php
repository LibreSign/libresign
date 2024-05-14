<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCP\Files\File;

interface ISignatureUuid {
	public function validateSignRequestUuid(string $uuid): void;
	public function validateRenewSigner(string $uuid): void;
	public function loadNextcloudFileFromSignRequestUuid(string $uuid): void;
	public function getSignRequestEntity(): ?SignRequestEntity;
	public function getFileEntity(): ?FileEntity;
	public function getNextcloudFile(): ?File;
}
