<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method void setFileType(string $fileType)
 * @method int getFileType()
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @method void setFileId(int $fileId)
 * @method int getFileId()
 */
class AccountFile extends Entity {
	public string $fileType = '';
	protected string $userId = '';
	protected int $fileId = 0;

	public function __construct() {
		$this->addType('fileType', Types::STRING);
		$this->addType('userId', Types::STRING);
		$this->addType('fileId', Types::INTEGER);
	}
}
