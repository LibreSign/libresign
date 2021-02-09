<?php

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setFile(int $fileId)
 * @method int getFile()
 */
class File extends Entity {
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