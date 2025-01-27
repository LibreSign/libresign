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
 * @method void setType(string $type)
 * @method string getType()
 * @method void setFileId(int $fileId)
 * @method int getFileId()
 * @method void setUserId(string $userId)
 * @method void setStarred(int $starred)
 * @method int getStarred()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getCreatedAt()
 * @method void setMetadata(array $metadata)
 * @method array|null getMetadata()
 */
class UserElement extends Entity {
	public int $id;
	public string $type;
	protected int $fileId;
	protected string $userId;
	public bool $starred;
	public \DateTime $createdAt;
	protected ?string $metadata;
	/** @var array{url: string, nodeId: non-negative-int}|null */
	public $file;
	public function __construct() {
		$this->addType('id', Types::INTEGER);
		$this->addType('type', Types::STRING);
		$this->addType('fileId', Types::INTEGER);
		$this->addType('userId', Types::STRING);
		$this->addType('starred', Types::INTEGER);
		$this->addType('createdAt', Types::DATETIME);
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

	/**
	 * @param \DateTime|string $createdAt
	 */
	public function setCreatedAt($createdAt): void {
		if (!$createdAt instanceof \DateTime) {
			$createdAt = new \DateTime($createdAt);
		}
		$this->createdAt = $createdAt;
		$this->markFieldUpdated('createdAt');
	}
}
