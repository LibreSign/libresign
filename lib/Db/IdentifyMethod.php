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
 * @method void setSignRequestId(int $signRequestId)
 * @method int getSignRequestId()
 * @method void setAttempts(int $attempts)
 * @method int getAttempts()
 * @method void setMandatory(int $mandatory)
 * @method int getMandatory()
 * @method void setIdentifierKey(string $identifierKey)
 * @method string getIdentifierKey()
 * @method void setIdentifierValue(string $identifierValue)
 * @method void setCode(string $code)
 * @method ?string getCode()
 * @method ?\DateTime getIdentifiedAtDate()
 * @method ?\DateTime getLastAttemptDate()
 * @method void setMetadata(array $metadata)
 * @method array getMetadata()
 */
class IdentifyMethod extends Entity {
	public int $signRequestId = 0;
	public int $mandatory = 0;
	public string $identifierKey = '';
	public string $identifierValue = '';
	public int $attempts = 0;
	public ?string $code = null;
	public ?\DateTime $identifiedAtDate = null;
	public ?\DateTime $lastAttemptDate = null;
	protected ?array $metadata = null;

	public function __construct() {
		$this->addType('signRequestId', Types::INTEGER);
		$this->addType('mandatory', Types::INTEGER);
		$this->addType('code', Types::STRING);
		$this->addType('identifierKey', Types::STRING);
		$this->addType('identifierValue', Types::STRING);
		$this->addType('attempts', Types::INTEGER);
		$this->addType('identifiedAtDate', Types::DATETIME);
		$this->addType('lastAttemptDate', Types::DATETIME);
		$this->addType('metadata', Types::JSON);
	}

	public function setIdentifiedAtDate(null|string|\DateTime $identifiedAtDate): void {
		if ($identifiedAtDate && !$identifiedAtDate instanceof \DateTime) {
			$identifiedAtDate = new \DateTime($identifiedAtDate);
		}
		$this->identifiedAtDate = $identifiedAtDate;
		$this->markFieldUpdated('identifiedAtDate');
	}

	public function isDeletedAccount(): bool {
		$metadata = $this->getMetadata();
		return isset($metadata['deleted_account']);
	}

	public function getIdentifierValue(): string {
		$metadata = $this->getMetadata();
		if (isset($metadata['deleted_account'])) {
			return $metadata['deleted_account']['email'] ?? $metadata['deleted_account']['account'];
		}
		return $this->identifierValue;
	}

	public function setLastAttemptDate(null|string|\DateTime $lastAttemptDate): void {
		if ($lastAttemptDate && !$lastAttemptDate instanceof \DateTime) {
			$lastAttemptDate = new \DateTime($lastAttemptDate);
		}
		$this->lastAttemptDate = $lastAttemptDate;
		$this->markFieldUpdated('lastAttemptDate');
	}
}
