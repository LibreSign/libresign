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
 * @method void setId(int $id)
 * @method int getId()
 * @method void setPermissionSetId(int $permissionSetId)
 * @method int getPermissionSetId()
 * @method void setTargetType(string $targetType)
 * @method string getTargetType()
 * @method void setTargetId(string $targetId)
 * @method string getTargetId()
 */
class PermissionSetBinding extends Entity {
	protected int $permissionSetId = 0;
	protected string $targetType = '';
	protected string $targetId = '';
	protected ?\DateTime $createdAt = null;

	public function __construct() {
		$this->addType('id', Types::INTEGER);
		$this->addType('permissionSetId', Types::INTEGER);
		$this->addType('targetType', Types::STRING);
		$this->addType('targetId', Types::STRING);
		$this->addType('createdAt', Types::DATETIME);
	}

	/**
	 * @param \DateTime|string $createdAt
	 */
	public function setCreatedAt($createdAt): void {
		if (!$createdAt instanceof \DateTime) {
			$createdAt = new \DateTime($createdAt, new \DateTimeZone('UTC'));
		}
		$this->createdAt = $createdAt;
		$this->markFieldUpdated('createdAt');
	}

	public function getCreatedAt(): ?\DateTime {
		return $this->createdAt;
	}
}
