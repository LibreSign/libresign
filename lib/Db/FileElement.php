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
 * @method int getId()
 * @method void setId(int $id)
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 * @method int getSignRequestId()
 * @method void setSignRequestId(int $signRequestId)
 * @method string getType()
 * @method void setType(string $type)
 * @method array getMetadata()
 * @method void setMetadata(array $metadata)
 * @method int getPage()
 * @method void setPage(int $page)
 * @method int getUrx()
 * @method void setUrx(int $urx)
 * @method int getUry()
 * @method void setUry(int $ury)
 * @method int getLlx()
 * @method void setLlx(int $llx)
 * @method int getLly()
 * @method void setLly(int $lly)
 * @method \DateTime getCreatedAt()
 */
class FileElement extends Entity {
	/** @var integer */
	public $id;

	/** @var integer */
	protected $fileId;

	/** @var int */
	protected $signRequestId;

	/** @var string */
	protected $type;

	/** @var string */
	protected $metadata;

	/** @var integer */
	protected $page;

	/** @var integer */
	protected $urx;

	/** @var integer */
	protected $ury;

	/** @var integer */
	protected $llx;

	/** @var integer */
	protected $lly;

	/** @var \DateTime */
	protected $createdAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('fileId', 'integer');
		$this->addType('signRequestId', 'integer');
		$this->addType('type', 'string');
		$this->addType('metadata', Types::JSON);
		$this->addType('page', 'integer');
		$this->addType('urx', 'integer');
		$this->addType('ury', 'integer');
		$this->addType('llx', 'integer');
		$this->addType('lly', 'integer');
		$this->addType('createdAt', 'datetime');
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
