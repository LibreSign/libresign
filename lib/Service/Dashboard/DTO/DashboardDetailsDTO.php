<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Dashboard\DTO;

final class DashboardDetailsDTO {

	public function __construct(
		public readonly DashboardStatsDTO $stats,

		public readonly array $entitlements,

		/** @var DashboardPaymentDTO[] */
		public readonly array $recentPayments,

		/** @var DashboardWorkflowItemDTO[] */
		public readonly array $myDocuments,

		/** @var DashboardWorkflowItemDTO[] */
		public readonly array $receivedDocuments,
	) {
	}

	public function toArray(): array {
	return [
		'stats' => $this->stats->toArray(),

		'entitlements' => $this->entitlements,

		'recentPayments' => array_map(
			fn ($payment) => $payment->toArray(),
			$this->recentPayments
		),

		'myDocuments' => array_map(
			fn ($document) => $document->toArray(),
			$this->myDocuments
		),

		'receivedDocuments' => array_map(
			fn ($document) => $document->toArray(),
			$this->receivedDocuments
		),
	];
}
}
