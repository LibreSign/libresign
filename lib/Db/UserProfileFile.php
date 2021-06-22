<?php

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

class UserProfileFile extends Entity {
	/** @var integer */
	public $id;

	/** @var string */
	protected $type;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('type', 'string');
	}
}