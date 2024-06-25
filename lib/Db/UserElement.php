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
 * @method void setId(int $id)
 * @method int getId()
 * @method void setType(string $type)
 * @method string getType()
 * @method void setFileId(int $fileId)
 * @method int getFileId()
 * @method void setUserId(string $userId)
 * @method void setStarred(int $starred)
 * @method int getStarred()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getCreatedAt()
 * @method void setMetadata(array $metadata)
 * @method array getMetadata()
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
	/** @var string */
	protected $metadata;
	/** @var array{url: string, nodeId: non-negative-int}|null */
	public $file;
	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('type', 'string');
		$this->addType('fileId', 'integer');
		$this->addType('userId', 'string');
		$this->addType('starred', 'integer');
		$this->addType('createdAt', 'datetime');
		$this->addType('metadata', Types::JSON);
	}

	public function isDeletedAccount(): bool {
		$metadata = $this->getMetadata();
		return isset($metadata['deleted_account']);
	}

	public function getUserId(): string {
		$metadata = $this->getMetadata();
		if (isset($metadata['deleted_account']['account'])) {
			return $metadata['deleted_account']['account'];
		}
		return $this->userId;
	}

	/**
	 * @param \DateTime|string $createdAt
	 */
	public function setCreatedAt($createdAt): void {
		if (!$createdAt instanceof \DateTime) {
			$createdAt = new \DateTime($createdAt);
		}
		$this->createdAt = $createdAt;
		$this->markFieldUpdated('createdAt');
	}
}
