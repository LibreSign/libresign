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
	protected $fileId;

	/** @var string */
	protected $userId;

	/** @var string */
	protected $created;

	/** @var string */
	protected $description;

	/** @var string */
	protected $name;

	/** @var string */
	protected $callback;

	/** @var integer */
	protected $enabled;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('fileId', 'integer');
		$this->addType('userId', 'string');
		$this->addType('created', 'string');
		$this->addType('description', 'string');
		$this->addType('name', 'string');
		$this->addType('callback', 'string');
		$this->addType('enabled', 'integer');
	}
}