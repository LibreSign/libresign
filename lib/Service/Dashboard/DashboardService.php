<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Dashboard;

use OCA\Libresign\Db\Payment;
use OCA\Libresign\Db\PaymentMapper;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Service\Dashboard\DTO\DashboardDetailsDTO;
use OCA\Libresign\Service\Dashboard\DTO\DashboardPaymentDTO;
use OCA\Libresign\Service\Dashboard\DTO\DashboardStatsDTO;
use OCA\Libresign\Service\Dashboard\Factory\DashboardWorkflowContextFactory;
use OCA\Libresign\Service\Dashboard\Mapper\DashboardWorkflowMapper;
use OCA\Libresign\Service\Entitlement\EntitlementService;
use OCA\Libresign\Service\Payment\AmountResolver;
use OCP\DB\Exception;
use OCP\IUser;

final class DashboardService
{

	public function __construct(
		private readonly SignRequestMapper $signRequestMapper,
		private readonly EntitlementService $entitlementService,
		private readonly PaymentMapper $paymentMapper,
		private readonly DashboardWorkflowContextFactory $workflowContextFactory,
		private readonly DashboardWorkflowMapper $workflowMapper,
		private readonly AmountResolver $amountResolver,
	) {}

	/**
	 * @throws Exception
	 */
	public function getDashboardDetails(
		IUser $user,
		?int $filesPage = 1,
		?int $filesLength = 5,
	): DashboardDetailsDTO {

		$userId = $user->getUID();

		$allFilesResult = $this->signRequestMapper
			->getFilesAssociatedFilesWithMe($user, [], 1, 1000);

		$allFiles = $allFilesResult['data'];

		$recentFilesResult = $this->signRequestMapper
			->getFilesAssociatedFilesWithMe(
				$user,
				[],
				$filesPage,
				$filesLength,
				[
					'sortBy' => 'created_at',
					'sortDirection' => 'desc',
				]
			);

		$recentFiles = $recentFilesResult['data'];

		$workflowContexts = $this->workflowContextFactory
			->buildMany($recentFiles, $user);

		$workflowItems = $this->workflowMapper
			->mapMany($workflowContexts);

		return new DashboardDetailsDTO(
			stats: $this->getStats($allFiles),
			entitlements: $this->getUserEntitlements($userId),
			recentPayments: $this->getRecentPayments($userId),
			myDocuments: $this->getMyDocuments(
				$workflowItems,
				$userId
			),
			receivedDocuments: $this->getReceivedDocuments(
				$workflowItems,
				$userId
			),
		);
	}

	/**
	 * @param array $workflowItems
	 */
	private function getMyDocuments(
		array $workflowItems,
		string $userId,
	): array {
		return array_values(
			array_filter(
				$workflowItems,
				fn($item) => $item->isOwner
			)
		);
	}

	/**
	 * @param array $workflowItems
	 */
	private function getReceivedDocuments(
		array $workflowItems,
		string $userId,
	): array {
		return array_values(
			array_filter(
				$workflowItems,
				fn($item) => !$item->isOwner
			)
		);
	}

	/**
	 * @throws Exception
	 */
	private function getRecentPayments(
		string $userId,
	): array {

		$payments = $this->paymentMapper
			->findAllByUserId($userId);

		$payments = array_slice($payments, 0, 5);

		return array_map(
			function (Payment $payment): DashboardPaymentDTO {

				$displayAmountMinor =
					$payment->getDisplayAmount();

				$displayCurrency =
					$payment->getDisplayCurrency();

				$displayAmount = null;
				$displayAmountFormatted = null;

				/**
				 * Convert stored minor units → major units.
				 *
				 * IMPORTANT:
				 * - DB stores minor units
				 * - FE consumes major/display values
				 */
				if (
					$displayAmountMinor !== null
					&& $displayCurrency !== null
				) {

					$displayAmount = $this->amountResolver
						->toMajorUnits(
							$displayAmountMinor,
							$displayCurrency
						);

					$displayAmountFormatted =
						$this->amountResolver->format(
							$displayAmountMinor,
							$displayCurrency
						);
				}

				return new DashboardPaymentDTO(
					amount: $payment->getAmount(),
					currency: $payment->getCurrency(),

					displayAmount: $displayAmount,

					displayCurrency: $displayCurrency,

					displayAmountFormatted: $displayAmountFormatted,

					status: $payment
						->getPaymentStatus()
						->value,

					provider: $payment->getProvider(),

					createdAt: $payment
						->getCreatedAt()?->format(DATE_ATOM),

					signUuid: $payment->getSignUuid(),
					signRequestId: $payment->getSignRequestId(),
				);
			},
			$payments
		);
	}

	private function getStats(
		array $files,
	): DashboardStatsDTO {

		$stats = [
			'totalDocuments' => count($files),
			'pendingDocuments' => 0,
			'completedDocuments' => 0,
			'draftDocuments' => 0,
		];

		foreach ($files as $file) {
			$status = $file->getStatusEnum();

			match ($status) {
				FileStatus::SIGNED => $stats['completedDocuments']++,
				FileStatus::DRAFT => $stats['draftDocuments']++,
				default => $stats['pendingDocuments']++,
			};
		}

		return new DashboardStatsDTO(
			totalDocuments: $stats['totalDocuments'],
			pendingDocuments: $stats['pendingDocuments'],
			completedDocuments: $stats['completedDocuments'],
			draftDocuments: $stats['draftDocuments'],
		);
	}

	/**
	 * @throws Exception
	 */
	private function getUserEntitlements(
		string $userId,
	): array {

		$entitlement = $this->entitlementService
			->getValid($userId, 'SIGN_DOCUMENT');

		return [
			'SIGN_DOCUMENT' => [
				'remainingUses' =>
				$entitlement?->getRemainingUses() ?? 0,
			],
		];
	}
}
