<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

use function json_decode;
use function json_encode;

/**
 * @method void setId(int $id)
 * @method int getId()
 * @method void setName(string $name)
 * @method string getName()
 * @method void setDescription(?string $description)
 * @method ?string getDescription()
 * @method void setScopeType(string $scopeType)
 * @method string getScopeType()
 * @method void setEnabled(int $enabled)
 * @method void setPriority(int $priority)
 * @method int getPriority()
 * @method void setCreatedAt(\DateTime|string $createdAt)
 * @method ?\DateTime getCreatedAt()
 * @method void setUpdatedAt(\DateTime|string $updatedAt)
 * @method ?\DateTime getUpdatedAt()
 */
class PermissionSet extends Entity {
	protected string $name = '';
	protected ?string $description = null;
	protected string $scopeType = 'organization';
	protected int $enabled = 1;
	protected int $priority = 0;
	protected string $policyJson = '{}';
	protected ?\DateTime $createdAt = null;
	protected ?\DateTime $updatedAt = null;

	public function __construct() {
		$this->addType('id', Types::INTEGER);
		$this->addType('name', Types::STRING);
		$this->addType('description', Types::TEXT);
		$this->addType('scopeType', Types::STRING);
		$this->addType('enabled', Types::SMALLINT);
		$this->addType('priority', Types::SMALLINT);
		$this->addType('policyJson', Types::TEXT);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('updatedAt', Types::DATETIME);
	}

	public function isEnabled(): bool {
		return $this->enabled === 1;
	}

	public function setEnabled(bool $enabled): void {
		$this->setter('enabled', [$enabled ? 1 : 0]);
	}

	/**
	 * @param array<string, mixed> $policyJson
	 */
	public function setPolicyJson(array $policyJson): void {
		$this->setter('policyJson', [json_encode($policyJson, JSON_THROW_ON_ERROR)]);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getDecodedPolicyJson(): array {
		$decoded = json_decode($this->policyJson, true);
		return is_array($decoded) ? $decoded : [];
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

	/**
	 * @param \DateTime|string $updatedAt
	 */
	public function setUpdatedAt($updatedAt): void {
		if (!$updatedAt instanceof \DateTime) {
			$updatedAt = new \DateTime($updatedAt, new \DateTimeZone('UTC'));
		}
		$this->updatedAt = $updatedAt;
		$this->markFieldUpdated('updatedAt');
	}

	public function getUpdatedAt(): ?\DateTime {
		return $this->updatedAt;
	}
}
