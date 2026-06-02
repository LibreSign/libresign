<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\DTO;

use InvalidArgumentException;

final class MobileMoneyChargeDTO
{
	public function __construct(
		// Provider reference (DPO TransactionToken)
		public readonly string $providerReference,

		// Normalized phone (E.164 digits, no +)
		public readonly string $phone,

		// REQUIRED for DPO execution
		public readonly string $mno,
		public readonly string $country,

		// Amount context (important for audit + future providers)
		public readonly float $amount,
		public readonly string $currency,
	) {
		$this->validate();
	}

	private function validate(): void
	{
		if ($this->providerReference === '') {
			throw new InvalidArgumentException('reference is required');
		}

		if ($this->phone === '') {
			throw new InvalidArgumentException('phone is required');
		}

		if ($this->mno === '') {
			throw new InvalidArgumentException('mno is required for charge');
		}

		if ($this->country === '') {
			throw new InvalidArgumentException('country is required for charge');
		}

		if ($this->amount <= 0) {
			throw new InvalidArgumentException('amount must be greater than 0');
		}

		if ($this->currency === '') {
			throw new InvalidArgumentException('currency is required');
		}
	}
}
