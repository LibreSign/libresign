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
 * @method void setId(int $id)
 * @method int getId()
 * @method void setNodeId(?int $nodeId)
 * @method int getNodeId()
 * @method void setSignedNodeId(int $nodeId)
 * @method int|null getSignedNodeId()
 * @method void setSignedHash(string $hash)
 * @method string|null getSignedHash()
 * @method void setUserId(string $userId)
 * @method int|null getUserId()
 * @method void setUuid(string $uuid)
 * @method string getUuid()
 * @method void setCreatedAt(int $createdAt)
 * @method int getCreatedAt()
 * @method void setName(string $name)
 * @method string getName()
 * @method void setCallback(string $callback)
 * @method string|null getCallback()
 * @method void setStatus(int $status)
 * @method int getStatus()
 * @method void setMetadata(array $metadata)
 * @method array|null getMetadata()
 */
class File extends Entity {
	public int $id;
	protected int $nodeId;
	protected string $uuid;
	protected int $createdAt;
	protected string $name;
	protected int $status;
	protected ?string $userId;
	protected ?int $signedNodeId;
	protected ?string $signedHash;
	protected ?string $callback;
	protected ?string $metadata;
	public const STATUS_NOT_LIBRESIGN_FILE = -1;
	public const STATUS_DRAFT = 0;
	public const STATUS_ABLE_TO_SIGN = 1;
	public const STATUS_PARTIAL_SIGNED = 2;
	public const STATUS_SIGNED = 3;
	public const STATUS_DELETED = 4;

	public function __construct() {
		$this->addType('id', Types::INTEGER);
		$this->addType('nodeId', Types::INTEGER);
		$this->addType('signedNodeId', Types::INTEGER);
		$this->addType('signedHash', Types::STRING);
		$this->addType('userId', Types::STRING);
		$this->addType('uuid', Types::STRING);
		$this->addType('createdAt', Types::INTEGER);
		$this->addType('name', Types::STRING);
		$this->addType('callback', Types::STRING);
		$this->addType('status', Types::INTEGER);
		$this->addType('metadata', Types::JSON);
	}

	public function isDeletedAccount(): bool {
		$metadata = $this->getMetadata();
		return isset($metadata['deleted_account']);
	}

	public function getUserId(): string {
		$metadata = $this->getMetadata();
		if (isset($metadata['deleted_account']['account'])) {
			return $metadata['deleted_account']['account'];
		}
		return $this->userId;
	}
}
