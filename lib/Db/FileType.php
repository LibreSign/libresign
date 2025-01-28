<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getType()
 * @method void setType(string $type)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getDescription()
 * @method void setDescription(string $description)
 */
class FileType extends Entity {
	public string $type = '';
	protected string $name = '';
	protected string $description = '';
	public function __construct() {
		$this->addType('type', Types::STRING);
		$this->addType('name', Types::STRING);
		$this->addType('description', Types::STRING);
	}
}
