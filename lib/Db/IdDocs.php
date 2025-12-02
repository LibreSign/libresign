<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setId(int $id)
 * @method int getId()
 * @method void setFileId(int $fileId)
 * @method int getFileId()
 * @method void setSignRequestId(?int $signRequestId)
 * @method int|null getSignRequestId()
 * @method void setUserId(?string $userId)
 * @method string|null getUserId()
 * @method void setFileType(string $fileType)
 * @method string getFileType()
 */
class IdDocs extends Entity {
	/** @var int */
	public $id;
	/** @var int */
	protected $fileId;
	/** @var int|null */
	protected $signRequestId;
	/** @var string|null */
	protected $userId;
	/** @var string */
	protected $fileType;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('fileId', 'integer');
		$this->addType('signRequestId', 'integer');
		$this->addType('userId', 'string');
		$this->addType('fileType', 'string');
	}
}
