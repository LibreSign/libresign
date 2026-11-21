<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setId(int $id)
 * @method int getId()
 * @method void setFileId(int $fileId)
 * @method int getFileId()
 * @method void setSignRequestId(?int $signRequestId)
 * @method int|null getSignRequestId()
 * @method void setUserId(?string $userId)
 * @method string|null getUserId()
 * @method void setFileType(string $fileType)
 * @method string getFileType()
 * @method void setRejectedAt(?\DateTime $rejectedAt)
 * @method ?\DateTime getRejectedAt()
 * @method void setRejectedBy(?string $rejectedBy)
 * @method ?string getRejectedBy()
 * @method void setRejectionReason(?string $rejectionReason)
 * @method ?string getRejectionReason()
 */
class IdDocs extends Entity {
	protected int $fileId = 0;
	protected ?int $signRequestId = null;
	protected ?string $userId = null;
	protected string $fileType = '';
	protected ?\DateTime $rejectedAt = null;
	protected ?string $rejectedBy = null;
	protected ?string $rejectionReason = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('fileId', 'integer');
		$this->addType('signRequestId', 'integer');
		$this->addType('userId', 'string');
		$this->addType('fileType', 'string');
		$this->addType('rejectedAt', 'datetime');
		$this->addType('rejectedBy', 'string');
		$this->addType('rejectionReason', 'string');
	}
}
