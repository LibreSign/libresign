<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCA\Libresign\Enum\CRLStatus;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method void setId(int $id)
 * @method int getId()
 * @method void setSerialNumber(string $serialNumber)
 * @method string getSerialNumber()
 * @method void setOwner(string $owner)
 * @method string getOwner()
 * @method void setReasonCode(?int $reasonCode)
 * @method ?int getReasonCode()
 * @method void setRevokedBy(?string $revokedBy)
 * @method ?string getRevokedBy()
 * @method void setRevokedAt(?\DateTime $revokedAt)
 * @method ?\DateTime getRevokedAt()
 * @method void setInvalidityDate(?\DateTime $invalidityDate)
 * @method ?\DateTime getInvalidityDate()
 * @method void setCrlNumber(?int $crlNumber)
 * @method ?int getCrlNumber()
 * @method void setIssuedAt(\DateTime $issuedAt)
 * @method \DateTime getIssuedAt()
 * @method void setValidTo(?\DateTime $validTo)
 * @method ?\DateTime getValidTo()
 * @method void setComment(?string $comment)
 * @method ?string getComment()
 * @method void setEngine(string $engine)
 * @method string getEngine()
 * @method void setInstanceId(?string $instanceId)
 * @method ?string getInstanceId()
 * @method void setGeneration(?int $generation)
 * @method ?int getGeneration()
 */
class Crl extends Entity {
	protected string $serialNumber = '';
	protected string $owner = '';
	protected string $status = 'issued';
	protected ?int $reasonCode = null;
	protected ?string $revokedBy = null;
	protected ?\DateTime $revokedAt = null;
	protected ?\DateTime $invalidityDate = null;
	protected ?int $crlNumber = null;
	protected ?\DateTime $issuedAt = null;
	protected ?\DateTime $validTo = null;
	protected ?string $comment = null;
	protected string $engine = '';
	protected ?string $instanceId = null;
	protected ?int $generation = null;

	public function __construct() {
		$this->addType('id', Types::BIGINT);
		$this->addType('serialNumber', Types::STRING);
		$this->addType('status', Types::STRING);
		$this->addType('reasonCode', Types::SMALLINT);
		$this->addType('crlNumber', Types::BIGINT);
		$this->addType('revokedAt', Types::DATETIME);
		$this->addType('invalidityDate', Types::DATETIME);
		$this->addType('issuedAt', Types::DATETIME);
		$this->addType('validTo', Types::DATETIME);
		$this->addType('comment', Types::STRING);
		$this->addType('engine', Types::STRING);
		$this->addType('instanceId', Types::STRING);
		$this->addType('generation', Types::BIGINT);
	}

	public function getStatus(): string {
		return $this->status;
	}

	public function setStatus(CRLStatus|string $status): void {
		$value = $status instanceof CRLStatus ? $status->value : $status;
		$this->setter('status', [$value]);
	}

	public function isRevoked(): bool {
		return CRLStatus::from($this->status) === CRLStatus::REVOKED;
	}

	public function isExpired(): bool {
		if ($this->validTo === null) {
			return false;
		}
		return $this->validTo < new \DateTime();
	}

	public function isValid(): bool {
		return !$this->isRevoked() && !$this->isExpired();
	}
}
