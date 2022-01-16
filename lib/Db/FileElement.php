<?php

declare(strict_types=1);

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 * @method int getFileUserId()
 * @method void setFileUserId(int $fileUserId)
 * @method string getType()
 * @method void setType(string $type)
 * @method string getMetadata()
 * @method void setMetadata(string $metadata)
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
	protected $fileUserId;

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
		$this->addType('fileUserId', 'integer');
		$this->addType('type', 'string');
		$this->addType('metadata', 'string');
		$this->addType('page', 'integer');
		$this->addType('urx', 'integer');
		$this->addType('ury', 'integer');
		$this->addType('llx', 'integer');
		$this->addType('lly', 'integer');
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
