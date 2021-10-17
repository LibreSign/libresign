<?php

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
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getCreatedAt()
 */
class UserElement extends Entity {

	/** @var integer */
	public $id;
	/** @var string */
	protected $type;
	/** @var integer */
	protected $fileId;
	/** @var string */
	protected $userId;
	/** @var \DateTime */
	protected $createdAt;
	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('type', 'string');
		$this->addType('fileId', 'integer');
		$this->addType('userId', 'string');
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
