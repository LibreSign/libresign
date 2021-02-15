<?php

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setId(int $uid)
 * @method int getId()
 * @method void setLibresignFileId(int $libresignFileId)
 * @method int getLibresignFileId()
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @method void setEmail(string $email)
 * @method string getEmail()
 * @method void setUuid(string $uuid)
 * @method string getUuid()
 * @method void setCreatedAt(string $createdAt)
 * @method string getCreatedAt()
 * @method void setSigned(string $signed)
 * @method string getSigned()
 * @method void setDisplayName(string $displayName)
 * @method string getDisplayName()
 * @method void setFullName(string $fullName)
 * @method string getFullName()
 */
class FileUser extends Entity {
	/** @var integer */
	public $id;

	/** @var integer */
	protected $libresignFileId;

	/** @var string */
	protected $userId;

	/** @var string */
	protected $uuid;

	/** @var string */
	protected $email;

	/** @var string */
	protected $displayName;

	/** @var string */
	protected $fullName;

	/** @var string */
	protected $createdAt;

	/** @var string */
	protected $signed;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('libresignFileId', 'integer');
		$this->addType('userId', 'string');
		$this->addType('uuid', 'string');
		$this->addType('email', 'string');
		$this->addType('displayName', 'string');
		$this->addType('fullName', 'string');
		$this->addType('createdAt', 'string');
		$this->addType('signed', 'string');
	}
}
