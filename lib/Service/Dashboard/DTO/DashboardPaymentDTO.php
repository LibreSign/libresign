<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Dashboard\DTO;

final class DashboardPaymentDTO {

	public function __construct(
		public readonly int $amount,
		public readonly string $currency,

		public readonly ?float $displayAmount,
		public readonly ?string $displayCurrency,
		public readonly ?string $displayAmountFormatted,

		public readonly string $status,
		public readonly string $provider,

		public readonly ?string $createdAt,
		public readonly ?string $signUuid,
		public readonly ?int $signRequestId,
	) {
	}

	public function toArray(): array {
	return [
		'amount' => $this->amount,
		'currency' => $this->currency,

		'displayAmount' => $this->displayAmount,
		'displayCurrency' => $this->displayCurrency,
		'displayAmountFormatted' => $this->displayAmountFormatted,

		'status' => $this->status,
		'provider' => $this->provider,

		'createdAt' => $this->createdAt,
		'signUuid' => $this->signUuid,
		'signRequestId' => $this->signRequestId,
	];
}
}
