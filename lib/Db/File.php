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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
 * @method int getSignedNodeId()
 * @method void setSignedHash(string $hash)
 * @method string getSignedHash()
 * @method void setUserId(string $userId)
 * @method void setUuid(string $uuid)
 * @method string getUuid()
 * @method void setCreatedAt(int $createdAt)
 * @method int getCreatedAt()
 * @method void setName(string $name)
 * @method string getName()
 * @method void setCallback(string $callback)
 * @method string getCallback()
 * @method void setStatus(int $status)
 * @method int getStatus()
 * @method void setMetadata(array $metadata)
 * @method array getMetadata()
 */
class File extends Entity {
	/** @var integer */
	public $id;

	/** @var integer */
	protected $nodeId;

	/** @var integer */
	protected $signedNodeId;

	/** @var string */
	protected $signedHash;

	/** @var string */
	protected $userId;

	/** @var string */
	protected $uuid;

	/** @var integer */
	protected $createdAt;

	/** @var string */
	protected $name;

	/** @var string */
	protected $callback;

	/** @var integer */
	protected $status;

	/** @var string */
	protected $metadata;

	public const STATUS_NOT_LIBRESIGN_FILE = -1;
	public const STATUS_DRAFT = 0;
	public const STATUS_ABLE_TO_SIGN = 1;
	public const STATUS_PARTIAL_SIGNED = 2;
	public const STATUS_SIGNED = 3;
	public const STATUS_DELETED = 4;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('nodeId', 'integer');
		$this->addType('signedNodeId', 'integer');
		$this->addType('signedHash', 'string');
		$this->addType('userId', 'string');
		$this->addType('uuid', 'string');
		$this->addType('createdAt', 'integer');
		$this->addType('name', 'string');
		$this->addType('callback', 'string');
		$this->addType('status', 'integer');
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
