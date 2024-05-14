<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setSignRequestId(int $signRequestId)
 * @method int getSignRequestId()
 * @method void setAttempts(int $attempts)
 * @method int getAttempts()
 * @method void setMandatory(int $mandatory)
 * @method int getMandatory()
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
	public $signRequestId;
	/** @var int */
	public $mandatory;
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
		$this->addType('signRequestId', 'integer');
		$this->addType('mandatory', 'int');
		$this->addType('code', 'string');
		$this->addType('identifierKey', 'string');
		$this->addType('identifierValue', 'string');
		$this->addType('attempts', 'int');
		$this->addType('identifiedAtDate', 'datetime');
		$this->addType('lastAttemptDate', 'datetime');
	}

	public function setIdentifiedAtDate(null|string|\DateTime $identifiedAtDate): void {
		if ($identifiedAtDate && !$identifiedAtDate instanceof \DateTime) {
			$identifiedAtDate = new \DateTime($identifiedAtDate);
		}
		$this->identifiedAtDate = $identifiedAtDate;
		$this->markFieldUpdated('identifiedAtDate');
	}

	public function setLastAttemptDate(null|string|\DateTime $lastAttemptDate): void {
		if ($lastAttemptDate && !$lastAttemptDate instanceof \DateTime) {
			$lastAttemptDate = new \DateTime($lastAttemptDate);
		}
		$this->lastAttemptDate = $lastAttemptDate;
		$this->markFieldUpdated('lastAttemptDate');
	}
}
