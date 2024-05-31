<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getType()
 * @method void setType(string $type)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getDescription()
 * @method void setDescription(string $description)
 */
class FileType extends Entity {
	/** @var string */
	public $type;

	/** @var string */
	protected $name;

	/** @var string */
	protected $description;

	public function __construct() {
		$this->addType('type', 'string');
		$this->addType('name', 'string');
		$this->addType('description', 'string');
	}
}
