<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setFileType(string $fileType)
 * @method int getFileType()
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @method void setFileId(int $fileId)
 * @method int getFileId()
 */
class AccountFile extends Entity {
	/** @var string */
	public $fileType;

	/** @var string */
	protected $userId;

	/** @var integer */
	protected $fileId;

	public function __construct() {
		$this->addType('fileType', 'string');
		$this->addType('userId', 'string');
		$this->addType('fileId', 'integer');
	}
}
