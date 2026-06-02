<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment;

use OCA\Libresign\Enum\PaymentProvider;

final class ProviderAmountNormaliser
{
	public function __construct(private AmountResolver $amountResolver) {}

	public function normalise(
		int $amountMinor,
		string $currency,
		PaymentProvider $provider
	): int|float {
		$major = $this->amountResolver->toMajorUnits($amountMinor, $currency);

		return match ($provider) {
			PaymentProvider::DARAJA => (int) round($major, 0),
			default => $major,
		};
	}
}
