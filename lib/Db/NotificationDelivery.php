<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method void setCorrelationId(string $value)
 * @method string getCorrelationId()
 * @method void setEventKey(string $value)
 * @method string getEventKey()
 * @method void setChannel(string $value)
 * @method string getChannel()
 * @method void setEventType(string $value)
 * @method string getEventType()
 * @method void setFileId(int $value)
 * @method int getFileId()
 * @method void setSignRequestId(?int $value)
 * @method ?int getSignRequestId()
 * @method void setOwnerId(string $value)
 * @method string getOwnerId()
 * @method void setRecipientEnc(string $value)
 * @method string getRecipientEnc()
 * @method void setMessageEnc(?string $value)
 * @method ?string getMessageEnc()
 * @method void setState(string $value)
 * @method string getState()
 * @method void setNextAttemptAt(?\DateTime $value)
 * @method ?\DateTime getNextAttemptAt()
 * @method void setCreatedAt(\DateTime $value)
 * @method \DateTime getCreatedAt()
 * @method void setUpdatedAt(\DateTime $value)
 * @method \DateTime getUpdatedAt()
 */
final class NotificationDelivery extends Entity {
	protected string $eventKey = '';
	protected string $correlationId = '';
	protected string $eventType = '';
	protected string $channel = '';
	protected int $fileId = 0;
	protected ?int $signRequestId = null;
	protected string $ownerId = '';
	protected string $recipientEnc = '';
	protected ?string $messageEnc = null;
	protected string $state = '';
	protected ?\DateTime $nextAttemptAt = null;
	protected ?\DateTime $createdAt = null;
	protected ?\DateTime $updatedAt = null;

	public function __construct() {
		$this->addType('fileId', Types::INTEGER);
		$this->addType('signRequestId', Types::INTEGER);
		$this->addType('nextAttemptAt', Types::DATETIME);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('updatedAt', Types::DATETIME);
	}
}
