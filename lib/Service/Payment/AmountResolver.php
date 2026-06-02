<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment;

final class AmountResolver
{
	/**
	 * Convert minor → major units (currency-aware)
	 *
	 * 8000 (KES) → 80.00
	 * 1500 (TZS) → 1500
	 */
	public function toMajorUnits(int $amountMinor, string $currency): float
	{
		$decimals = CurrencyConfig::decimals($currency);

		if ($decimals === 0) {
			return (float) $amountMinor;
		}

		return round($amountMinor / (10 ** $decimals), $decimals);
	}

	/**
	 * Convert major → minor units (currency-aware)
	 *
	 * 80.00 (KES) → 8000
	 * 1500 (TZS) → 1500
	 */
	public function toMinorUnits(float $amountMajor, string $currency): int
	{
		$decimals = CurrencyConfig::decimals($currency);

		if ($decimals === 0) {
			return (int) round($amountMajor, 0);
		}

		return (int) round($amountMajor * (10 ** $decimals), 0);
	}

	/**
	 * Format for display (currency-aware)
	 *
	 * 8000 (KES) → "80.00"
	 * 1500 (TZS) → "1500"
	 */
	public function format(int $amountMinor, string $currency): string
	{
		$decimals = CurrencyConfig::decimals($currency);

		if ($decimals === 0) {
			return number_format($amountMinor, 0);
		}

		return number_format(
			$this->toMajorUnits($amountMinor, $currency),
			$decimals
		);
	}
}
