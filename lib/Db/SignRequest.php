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
 * @method void setId(int $uid)
 * @method int getId()
 * @method void setFileId(int $fileId)
 * @method ?int getFileId()
 * @method void setUuid(string $uuid)
 * @method string getUuid()
 * @method void setDescription(string $description)
 * @method ?string getDescription()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getCreatedAt()
 * @method void setSigned(\DateTime $signed)
 * @method ?\DateTime getSigned()
 * @method void setSignedHash(string $hash)
 * @method ?string getSignedHash()
 * @method void setDisplayName(string $displayName)
 * @method string getDisplayName()
 * @method void setMetadata(array $metadata)
 * @method ?array getMetadata()
 */
class SignRequest extends Entity {
	protected ?int $fileId = null;
	protected string $uuid = '';
	protected string $displayName = '';
	protected ?string $description = null;
	protected ?\DateTime $createdAt = null;
	protected ?\DateTime $signed = null;
	protected ?string $signedHash = null;
	protected ?array $metadata = null;
	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('fileId', 'integer');
		$this->addType('uuid', 'string');
		$this->addType('displayName', 'string');
		$this->addType('description', 'string');
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('signed', Types::DATETIME);
		$this->addType('signedHash', 'string');
		$this->addType('metadata', Types::JSON);
	}
}
