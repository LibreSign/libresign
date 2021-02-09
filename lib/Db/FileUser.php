<?php

namespace OCA\Files\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setFileUser(int $uid)
 * @method int getFile()
 */
class FileUser extends Entity {
	/** @var integer */
	protected $id;

	/** @var integer */
	protected $libresignFileId;

	/** @var string */
	protected $userId;

	/** @var string */
	protected $created;

	/** @var string */
	protected $signed;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('libresignFileId', 'integer');
		$this->addType('userId', 'string');
		$this->addType('uuid', 'string');
		$this->addType('created', 'string');
		$this->addType('signed', 'string');
	}
}