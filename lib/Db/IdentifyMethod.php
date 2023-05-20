<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setFileUserId(int $fileUserId)
 * @method int getFileUserId()
 * @method void setMethod(string $method)
 * @method string getMethod()
 * @method void setAttempts(int $attempts)
 * @method int getAttempts()
 * @method void setDefault(int $default)
 * @method int getDefault()
 * @method void setIdentifierKey(string $identifierKey)
 * @method string getIdentifierKey()
 * @method void setIdentifierValue(string $identifierValue)
 * @method string getIdentifierValue()
 * @method void setCode(string $code)
 * @method string getCode()
 * @method ?\DateTime getIdentifiedAtDate()
 * @method ?\DateTime getLastAttemptDate()
 */
class IdentifyMethod extends Entity {
	/** @var integer */
	public $fileUserId;
	/** @var string */
	public $method;
	/** @var int */
	public $default;
	/** @var string */
	public $code;
	/** @var string */
	public $identifierKey;
	/** @var string */
	public $identifierValue;
	/** @var int */
	public $attempts;
	/** @var ?\DateTime */
	public $identifiedAtDate;
	/** @var ?\DateTime */
	public $lastAttemptDate;

	public function __construct() {
		$this->addType('fileUserId', 'integer');
		$this->addType('method', 'string');
		$this->addType('default', 'int');
		$this->addType('code', 'string');
		$this->addType('identifierKey', 'string');
		$this->addType('identifierValue', 'string');
		$this->addType('attempts', 'int');
		$this->addType('identifiedAtDate', 'datetime');
		$this->addType('lastAttemptDate', 'datetime');
	}

	/**
	 * @param \DateTime|string $createdAt
	 */
	public function setIdentifiedAtDate(?string $identifiedAtDate): void {
		if ($identifiedAtDate && !$identifiedAtDate instanceof \DateTime) {
			$identifiedAtDate = new \DateTime($identifiedAtDate);
		}
		$this->identifiedAtDate = $identifiedAtDate;
		$this->markFieldUpdated('identifiedAtDate');
	}

	/**
	 * @param \DateTime|string $createdAt
	 */
	public function setLastAttemptDate(?string $lastAttemptDate): void {
		if ($lastAttemptDate && !$lastAttemptDate instanceof \DateTime) {
			$lastAttemptDate = new \DateTime($lastAttemptDate);
		}
		$this->lastAttemptDate = $lastAttemptDate;
		$this->markFieldUpdated('lastAttemptDate');
	}
}
