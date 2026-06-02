<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Dashboard\Factory;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\Payment;
use OCA\Libresign\Db\PaymentMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\Dashboard\Resolver\DashboardParticipantResolver;
use OCA\Libresign\Service\Dashboard\ValueObject\DashboardWorkflowContext;
use OCP\DB\Exception;
use OCP\IUser;
use OCP\IUserManager;

final class DashboardWorkflowContextFactory {

	public function __construct(
		private readonly SignRequestMapper $signRequestMapper,
		private readonly PaymentMapper $paymentMapper,
		private readonly DashboardParticipantResolver $participantResolver,
		private readonly IUserManager $userManager,
	) {
	}

	/**
	 * @throws Exception
	 */
	public function build(
		File $file,
		IUser $user,
	): DashboardWorkflowContext {
		$signRequests = $this->signRequestMapper
			->getByFileId($file->getId());

		$identifyMethods = $this->signRequestMapper
			->getIdentifyMethodsFromSigners($signRequests);

		$participantContext = $this->participantResolver
			->resolve($signRequests, $identifyMethods, $user);

		$owner = $this->userManager
			->get($file->getUserId());

		$requesterName =
			$owner?->getDisplayName()
			?? $file->getUserId();

		$isOwner =
			$file->getUserId() === $user->getUID();

		$isCompleted =
			$file->getStatusEnum() === FileStatus::SIGNED;

		$isDraft =
			$file->getStatusEnum() === FileStatus::DRAFT;

		$payment = $this->resolvePayment(
			$participantContext->signRequest?->getId()
		);

		return new DashboardWorkflowContext(
			file: $file,
			requesterName: $requesterName,
			signRequests: $signRequests,
			payment: $payment,
			participantContext: $participantContext,
			isOwner: $isOwner,
			isCompleted: $isCompleted,
			isDraft: $isDraft,
		);
	}

	/**
	 * @param File[] $files
	 * @return DashboardWorkflowContext[]
	 * @throws Exception
	 */
	public function buildMany(
		array $files,
		IUser $user,
	): array {
		return array_map(
			fn (File $file) => $this->build($file, $user),
			$files
		);
	}

	/**
	 * Payments are participant-scoped.
	 *
	 * We intentionally resolve payments through SignRequest
	 * instead of File UUID to avoid ambiguous document-level
	 * payment semantics.
	 *
	 * @throws Exception
	 */
	private function resolvePayment(
		?int $signRequestId,
	): ?Payment {
		if (!$signRequestId) {
			return null;
		}

		return $this->paymentMapper
			->findLatestPendingBySignRequestId($signRequestId)
			?? $this->paymentMapper
				->findLatestPaidBySignRequestId($signRequestId);
	}
}
