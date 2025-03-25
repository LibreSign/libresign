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
	protected \DateTime $createdAt = 0;
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
