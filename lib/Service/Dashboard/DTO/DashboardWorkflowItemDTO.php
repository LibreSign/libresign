<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Dashboard\DTO;

use OCA\Libresign\Enum\DashboardWorkflowAction;
use OCA\Libresign\Enum\DashboardWorkflowStatus;

final class DashboardWorkflowItemDTO {

	public function __construct(
		public readonly string $documentName,
		public readonly string $fileUuid,
		public readonly int $fileId,
		public readonly int $nodeId,

		public readonly DashboardWorkflowStatus $status,
		public readonly string $statusLabel,

		public readonly DashboardWorkflowAction $primaryAction,

		public readonly bool $canAct,
		public readonly bool $completed,

		public readonly bool $isOwner,
		public readonly bool $isSigner,

		public readonly ?string $requesterName,

		public readonly ?string $createdAt,
		public readonly ?string $updatedAt,

		/**
		 * @var array<DashboardSignerDTO>
		 */
		public array $signers = [],
	) {
	}

	public function toArray(): array {
	return [
		'documentName' => $this->documentName,
		'fileUuid' => $this->fileUuid,
		'fileId' => $this->fileId,
		'nodeId' => $this->nodeId,

		'status' => $this->status->value,
		'statusLabel' => $this->statusLabel,

		'primaryAction' => $this->primaryAction->value,

		'canAct' => $this->canAct,
		'completed' => $this->completed,

		'isOwner' => $this->isOwner,
		'isSigner' => $this->isSigner,

		'requesterName' => $this->requesterName,

		'createdAt' => $this->createdAt,
		'updatedAt' => $this->updatedAt,

		'signers' => array_map(
			fn ($signer) => $signer->toArray(),
			$this->signers
		),
	];
   }
}
