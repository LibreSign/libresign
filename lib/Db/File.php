<?php

declare(strict_types=1);

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setId(int $id)
 * @method int getId()
 * @method void setNodeId(int $nodeId)
 * @method int getNodeId()
 * @method void setSignedNodeId(int $nodeId)
 * @method int getSignedNodeId()
 * @method void setUserId(int $userId)
 * @method int getUserId()
 * @method void setUuid(int $uuid)
 * @method int getUuid()
 * @method void setCreatedAt(string $createdAt)
 * @method string getCreatedAt()
 * @method void setName(string $name)
 * @method string getName()
 * @method void setCallback(string $callback)
 * @method string getCallback()
 * @method void setEnabled(int $enabled)
 * @method int getEnabled()
 * @method void setPages(int $pages)
 * @method int getPages()
 */
class File extends Entity {
	/** @var integer */
	public $id;

	/** @var integer */
	protected $nodeId;

	/** @var integer */
	protected $signedNodeId;

	/** @var string */
	protected $userId;

	/** @var string */
	protected $uuid;

	/** @var string */
	protected $createdAt;

	/** @var string */
	protected $name;

	/** @var string */
	protected $callback;

	/** @var integer */
	protected $enabled;

	/** @var string */
	protected $metadata;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('nodeId', 'integer');
		$this->addType('userId', 'string');
		$this->addType('uuid', 'string');
		$this->addType('createdAt', 'string');
		$this->addType('name', 'string');
		$this->addType('callback', 'string');
		$this->addType('enabled', 'integer');
		$this->addType('metadata', 'string');
	}
}
