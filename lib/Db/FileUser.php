<?php

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method void setId(int $uid)
 * @method int getId()
 * @method void setFileId(int $fileId)
 * @method int getFileId()
 * @method void setUuid(string $uuid)
 * @method string getUuid()
 * @method void setDescription(string $description)
 * @method string getDescription()
 * @method void setCreatedAt(string $createdAt)
 * @method string getCreatedAt()
 * @method void setSigned(string $signed)
 * @method string getSigned()
 * @method void setDisplayName(string $displayName)
 * @method string getDisplayName()
 * @method void setFullName(string $fullName)
 * @method string getFullName()
 * @method void setCode(string $code)
 * @method string getCode()
 * @method void setMetadata(array $metadata)
 * @method string getMetadata()
 */
class FileUser extends Entity {
	/** @var integer */
	public $id;

	/** @var integer */
	protected $fileId;

	/** @var string */

	/** @var string */
	protected $uuid;

	/** @var string */

	/** @var string */
	protected $displayName;

	/** @var string */
	protected $fullName;

	/** @var string */
	protected $description;

	/** @var string */
	protected $createdAt;

	/** @var string */
	protected $signed;

	/** @var string */
	protected $code;

	/** @var string */
	protected $metadata;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('fileId', 'integer');
		$this->addType('uuid', 'string');
		$this->addType('displayName', 'string');
		$this->addType('fullName', 'string');
		$this->addType('description', 'string');
		$this->addType('createdAt', 'string');
		$this->addType('signed', 'string');
		$this->addType('code', 'string');
		$this->addType('metadata', Types::JSON);
	}
}
