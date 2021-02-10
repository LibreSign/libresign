<?php

declare(strict_types=1);

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setId(int $id)
 * @method int getId()
 * @method void setFileId(int $fileId)
 * @method int getFileId()
 * @method void setCreatedAt(string $createdAt)
 * @method string getCreatedAt()
 * @method void setDescription(string $description)
 * @method string getDescription()
 * @method void setName(string $name)
 * @method string getName()
 * @method void setCallback(string $callback)
 * @method string getCallback()
 * @method void setEnabled(int $enabled)
 * @method int getEnabled()
 */
class File extends Entity {
	/** @var integer */
	public $id;

	/** @var integer */
	protected $fileId;

	/** @var string */
	protected $userId;

	/** @var string */
	protected $createdAt;

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
		$this->addType('createdAt', 'string');
		$this->addType('description', 'string');
		$this->addType('name', 'string');
		$this->addType('callback', 'string');
		$this->addType('enabled', 'integer');
	}
}
