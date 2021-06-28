<?php

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setFileType(string $fileType)
 * @method int getFileType()
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @method void setFileId(int $fileId)
 * @method string getFileId()
 */
class AccountFile extends Entity {
	/** @var string */
	public $fileType;

	/** @var string */
	protected $userId;

	/** @var integer */
	protected $fileId;

	public function __construct() {
		$this->addType('fileType', 'string');
		$this->addType('userId', 'string');
		$this->addType('fileId', 'integer');
	}
}
