<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\DTO;

use InvalidArgumentException;
use OCA\Libresign\Enum\PaymentCapability;
use OCA\Libresign\Enum\PaymentFlowMode;
use OCA\Libresign\Enum\PaymentProvider;
use OCA\Libresign\Enum\ResolutionConfidence;

final class MnoRoutingResultDTO
{
	public function __construct(
		public readonly PaymentCapability $capability,
		public readonly PaymentProvider $preferredProvider,
		public readonly ?string $dpoMnoKey,
		public readonly PaymentFlowMode $mode,
		public readonly ?string $currency,
		public readonly ?string $altCurrency,
		public readonly ?float $minAmount,
		public readonly ?float $maxAmount,
		public readonly ?bool $supportsDecimals,
		public readonly ResolutionConfidence $confidence,
		public readonly ?string $notes,
		public readonly ?string $country,
		public readonly ?string $region,
	) {
		$this->validate();
	}

	/**
	 * Domain Validation (STRICT)
	 */
	private function validate(): void
	{
		$this->validateAmounts();
		$this->validateCurrencyConsistency();
	}

	private function validateAmounts(): void
	{
		if ($this->minAmount !== null && $this->maxAmount !== null) {
			if ($this->minAmount > $this->maxAmount) {
				throw new InvalidArgumentException(
					'minAmount cannot be greater than maxAmount'
				);
			}
		}

		if ($this->minAmount !== null && $this->minAmount < 0) {
			throw new InvalidArgumentException('minAmount cannot be negative');
		}

		if ($this->maxAmount !== null && $this->maxAmount < 0) {
			throw new InvalidArgumentException('maxAmount cannot be negative');
		}
	}

	private function validateCurrencyConsistency(): void
	{
		/**
		 * If limits are defined → currency must exist
		 */
		if (
			($this->minAmount !== null || $this->maxAmount !== null)
			&& $this->currency === null
		) {
			throw new InvalidArgumentException(
				'Currency must be defined when min/max amounts are set'
			);
		}
	}

	/**
	 * Domain Helpers
	 */

	public function isHighConfidence(): bool
	{
		return $this->confidence->isHigh();
	}

	public function isAmbiguous(): bool
	{
		return $this->confidence->isAmbiguous();
	}

	public function requiresUserSelection(): bool
	{
		return $this->confidence->requiresUserSelection()
			|| $this->dpoMnoKey === null;
	}

	public function supportsAutoCharge(): bool
	{
		return $this->mode === PaymentFlowMode::STK_PUSH
			&& $this->confidence->isHigh()
			&& $this->dpoMnoKey !== null;
	}

	/**
	 * Validate amount against constraints
	 */
	public function validateAmount(float $amount): void
	{
		if ($this->minAmount !== null && $amount < $this->minAmount) {
			throw new InvalidArgumentException('Amount below minimum allowed');
		}

		if ($this->maxAmount !== null && $amount > $this->maxAmount) {
			throw new InvalidArgumentException('Amount exceeds maximum allowed');
		}

		if ($this->supportsDecimals === false && floor($amount) !== $amount) {
			throw new InvalidArgumentException('Decimals not allowed');
		}
	}

	/**
	 * Serialization (API safe)
	 */
	public function toArray(): array
{
	return [
		'capability'        => $this->capability->value,
		'preferredProvider' => $this->preferredProvider->value,
		'dpoMnoKey'         => $this->dpoMnoKey,
		'mode'              => $this->mode->value,
		'currency'          => $this->currency,
		'altCurrency'       => $this->altCurrency,
		'minAmount'         => $this->minAmount,
		'maxAmount'         => $this->maxAmount,
		'supportsDecimals'  => $this->supportsDecimals,
		'confidence'        => $this->confidence->value,
		'notes'             => $this->notes,
		'country'			=> $this->country,
		'region'			=> $this->region,
	];
}

	/**
	 * Debug-safe snapshot
	 */
	public function __toString(): string
	{
		return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
	}
}
