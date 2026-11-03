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
 * @method void setDeliveryId(int $value)
 * @method int getDeliveryId()
 * @method void setState(string $value)
 * @method string getState()
 * @method void setReasonCode(?string $value)
 * @method ?string getReasonCode()
 * @method void setStartedAt(\DateTime $value)
 * @method \DateTime getStartedAt()
 * @method void setFinishedAt(?\DateTime $value)
 * @method ?\DateTime getFinishedAt()
 */
final class NotificationAttempt extends Entity {
	protected int $deliveryId = 0;
	protected string $state = '';
	protected ?string $reasonCode = null;
	protected ?\DateTime $startedAt = null;
	protected ?\DateTime $finishedAt = null;

	public function __construct() {
		$this->addType('deliveryId', Types::INTEGER);
		$this->addType('startedAt', Types::DATETIME);
		$this->addType('finishedAt', Types::DATETIME);
	}
}
