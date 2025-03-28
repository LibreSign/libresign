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
 * @method ?array getMetadata()
 * @method void setMetadata(array $metadata)
 * @method int getPage()
 * @method void setPage(int $page)
 * @method int getUrx()
 * @method void setUrx(int $urx)
 * @method int getUry()
 * @method void setUry(int $ury)
 * @method ?int getLlx()
 * @method void setLlx(int $llx)
 * @method ?int getLly()
 * @method void setLly(int $lly)
 * @method ?\DateTime getCreatedAt()
 */
class FileElement extends Entity {
	protected int $fileId = 0;
	protected int $signRequestId = 0;
	protected string $type = '';
	protected ?array $metadata = null;
	protected int $page = 0;
	protected int $urx = 0;
	protected int $ury = 0;
	protected ?int $llx = null;
	protected ?int $lly = null;
	protected ?\DateTime $createdAt = null;
	public function __construct() {
		$this->addType('id', Types::INTEGER);
		$this->addType('fileId', Types::INTEGER);
		$this->addType('signRequestId', Types::INTEGER);
		$this->addType('type', Types::STRING);
		$this->addType('metadata', Types::JSON);
		$this->addType('page', Types::INTEGER);
		$this->addType('urx', Types::INTEGER);
		$this->addType('ury', Types::INTEGER);
		$this->addType('llx', Types::INTEGER);
		$this->addType('lly', Types::INTEGER);
		$this->addType('createdAt', Types::DATETIME);
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
