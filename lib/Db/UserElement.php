<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setId(int $id)
 * @method int getId()
 * @method void setType(string $type)
 * @method string getType()
 * @method void setFileId(int $fileId)
 * @method int getFileId()
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @method void setStarred(int $starred)
 * @method int getStarred()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getCreatedAt()
 */
class UserElement extends Entity {
	/** @var integer */
	public $id;
	/** @var string */
	public $type;
	/** @var integer */
	protected $fileId;
	/** @var string */
	protected $userId;
	/** @var bool */
	public $starred;
	/** @var \DateTime */
	public $createdAt;
	/** @var array{url: string, nodeId: non-negative-int}|null */
	public $file;
	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('type', 'string');
		$this->addType('fileId', 'integer');
		$this->addType('userId', 'string');
		$this->addType('starred', 'integer');
		$this->addType('createdAt', 'datetime');
	}

	public function setCreatedAt($createdAt): void {
		if (!$createdAt instanceof \DateTime) {
			$createdAt = new \DateTime($createdAt);
		}
		$this->createdAt = $createdAt;
		$this->markFieldUpdated('createdAt');
	}
}
