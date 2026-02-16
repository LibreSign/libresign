<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\NodeType;
use OCA\Libresign\Enum\SignatureFlow;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method void setId(int $id)
 * @method int getId()
 * @method void setNodeId(?int $nodeId)
 * @method ?int getNodeId()
 * @method void setSignedNodeId(?int $nodeId)
 * @method ?int getSignedNodeId()
 * @method void setSignedHash(?string $hash)
 * @method ?string getSignedHash()
 * @method void setUserId(?string $userId)
 * @method ?string getUserId()
 * @method void setUuid(string $uuid)
 * @method string getUuid()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getCreatedAt()
 * @method void setName(string $name)
 * @method non-falsy-string getName()
 * @method void setCallback(string $callback)
 * @method ?string getCallback()
 * @method void setStatus(int $status)
 * @method int getStatus()
 * @method void setMetadata(array $metadata)
 * @method ?array getMetadata()
 * @method void setModificationStatus(int $modificationStatus)
 * @method int getModificationStatus()
 * @method void setSignatureFlow(int $signatureFlow)
 * @method int getSignatureFlow()
 * @method void setDocmdpLevel(int $docmdpLevel)
 * @method int getDocmdpLevel()
 * @method void setNodeType(string $nodeType)
 * @method 'file'|'envelope' getNodeType()
 * @method void setParentFileId(?int $parentFileId)
 * @method ?int getParentFileId()
 */
class File extends Entity {
	protected ?int $nodeId = null;
	protected string $uuid = '';
	protected ?\DateTime $createdAt = null;
	protected string $name = '';
	protected ?int $status = null;
	protected ?string $userId = null;
	protected ?int $signedNodeId = null;
	protected ?string $signedHash = null;
	protected ?string $callback = null;
	protected ?array $metadata = null;
	protected int $modificationStatus = 0;
	protected int $signatureFlow = SignatureFlow::NUMERIC_NONE;
	protected int $docmdpLevel = 0;
	protected string $nodeType = 'file';
	protected ?int $parentFileId = null;

	public const MODIFICATION_UNCHECKED = 0;
	public const MODIFICATION_UNMODIFIED = 1;
	public const MODIFICATION_ALLOWED = 2;
	public const MODIFICATION_VIOLATION = 3;

	public function __construct() {
		$this->addType('id', Types::INTEGER);
		$this->addType('nodeId', Types::INTEGER);
		$this->addType('signedNodeId', Types::INTEGER);
		$this->addType('signedHash', Types::STRING);
		$this->addType('userId', Types::STRING);
		$this->addType('uuid', Types::STRING);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('name', Types::STRING);
		$this->addType('callback', Types::STRING);
		$this->addType('status', Types::INTEGER);
		$this->addType('metadata', Types::JSON);
		$this->addType('modificationStatus', Types::SMALLINT);
		$this->addType('signatureFlow', Types::SMALLINT);
		$this->addType('docmdpLevel', Types::SMALLINT);
		$this->addType('nodeType', Types::STRING);
		$this->addType('parentFileId', Types::INTEGER);
	}

	public function isDeletedAccount(): bool {
		$metadata = $this->getMetadata();
		return isset($metadata['deleted_account']);
	}

	public function getUserId(): string {
		$metadata = $this->getMetadata();
		return $metadata['deleted_account']['account'] ?? $this->userId ?? '';
	}

	public function getStatusEnum(): FileStatus {
		return FileStatus::from($this->status ?? FileStatus::DRAFT->value);
	}

	public function setStatusEnum(FileStatus $status): void {
		$this->setStatus($status->value);
	}

	public function getSignatureFlowEnum(): \OCA\Libresign\Enum\SignatureFlow {
		return \OCA\Libresign\Enum\SignatureFlow::fromNumeric($this->signatureFlow);
	}

	public function setSignatureFlowEnum(\OCA\Libresign\Enum\SignatureFlow $flow): void {
		$this->setSignatureFlow($flow->toNumeric());
	}

	public function getDocmdpLevelEnum(): \OCA\Libresign\Enum\DocMdpLevel {
		return \OCA\Libresign\Enum\DocMdpLevel::tryFrom($this->docmdpLevel) ?? \OCA\Libresign\Enum\DocMdpLevel::NOT_CERTIFIED;
	}

	public function setDocmdpLevelEnum(\OCA\Libresign\Enum\DocMdpLevel $level): void {
		$this->setDocmdpLevel($level->value);
	}

	public function getNodeTypeEnum(): NodeType {
		return NodeType::from($this->nodeType);
	}

	public function setNodeTypeEnum(NodeType $nodeType): void {
		$this->setNodeType($nodeType->value);
	}

	public function isEnvelope(): bool {
		return $this->getNodeTypeEnum()->isEnvelope();
	}

	public function hasParent(): bool {
		return $this->parentFileId !== null;
	}
}
