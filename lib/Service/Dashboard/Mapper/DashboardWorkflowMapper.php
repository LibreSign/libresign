<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Dashboard\Mapper;

use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\Dashboard\DTO\DashboardSignerDTO;
use OCA\Libresign\Service\Dashboard\DTO\DashboardWorkflowItemDTO;
use OCA\Libresign\Service\Dashboard\Resolver\DashboardWorkflowResolver;
use OCA\Libresign\Service\Dashboard\ValueObject\DashboardWorkflowContext;

use OCP\IL10N;

final class DashboardWorkflowMapper
{


	public function __construct(
		private readonly DashboardWorkflowResolver $workflowResolver,
		private IL10N $l10n,
	) {}

	public function map(
		DashboardWorkflowContext $context,

	): DashboardWorkflowItemDTO {

		$status = $this->workflowResolver
			->resolveStatus($context);

		$primaryAction = $this->workflowResolver
			->resolvePrimaryAction($context);

		return new DashboardWorkflowItemDTO(
			documentName: $context->file->getName(),
			fileUuid: $context->file->getUuid(),
			fileId: $context->file->getId(),
			nodeId: $context->file->getNodeId(),

			status: $status,

			statusLabel: $this->workflowResolver
				->resolveStatusLabel($context),

			primaryAction: $primaryAction,

			canAct: $this->workflowResolver
				->canUserAct($context),

			completed: $context->isCompleted,

			isOwner: $context->isOwner,

			isSigner: $context
				->participantContext
				->isSigner,

			requesterName: $context->requesterName,

			createdAt: $context
				->file
				->getCreatedAt()?->format(DATE_ATOM),

			updatedAt: null,

			signers: array_map(
				fn($signRequest) => new DashboardSignerDTO(
					displayName: $signRequest->getDisplayName(),

					status: $signRequest
						->getStatusEnum()
						->name,

					canRemind: $context->isOwner
						&& $signRequest->getStatusEnum()
						!== SignRequestStatus::SIGNED,

					canRequestSignature: false,

					me: $context->participantContext->signRequest?->getId()
						=== $signRequest->getId(),
				),
				$context->signRequests
			),
		);
	}

	/**
	 * @param DashboardWorkflowContext[] $contexts
	 * @return DashboardWorkflowItemDTO[]
	 */
	public function mapMany(
		array $contexts,
	): array {
		return array_map(
			fn(DashboardWorkflowContext $context) => $this->map($context),
			$contexts
		);
	}
}
