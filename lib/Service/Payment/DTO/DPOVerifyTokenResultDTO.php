<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\DTO;

final class DPOVerifyTokenResultDTO
{
	public function __construct(
		// Raw DPO result code
		public readonly string $resultCode,

		// Internal mapped status
		// SUCCESS | PENDING | FAILED
		public readonly string $status,

		// Human explanation from DPO
		public readonly ?string $explanation = null,

		// Transaction context
		public readonly ?string $transactionCurrency = null,
		public readonly ?float $transactionAmount = null,

		// Final settlement values (important for FX-aware flows)
		public readonly ?string $transactionFinalCurrency = null,
		public readonly ?float $transactionFinalAmount = null,

		// Approval / reconciliation
		public readonly ?string $approvalCode = null,

		// Mobile-specific reconciliation state
		public readonly ?string $mobilePaymentRequest = null,

		// Fraud analysis
		public readonly ?string $fraudCode = null,
		public readonly ?string $fraudExplanation = null,

		// Customer context
		public readonly ?string $customerPhone = null,
		public readonly ?string $customerCountry = null,

		// Settlement
		public readonly ?string $settlementDate = null,
		public readonly ?float $netAmount = null,

		// Full raw response for audit/debugging
		public readonly array $raw = [],
	) {}
}
