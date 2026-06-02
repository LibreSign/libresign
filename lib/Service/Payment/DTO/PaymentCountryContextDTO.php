<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\DTO;

final class PaymentCountryContextDTO
{
	public function __construct(
		public readonly string $region,        // KE
		public readonly string $country,       // kenya
		public readonly string $currency,      // KES
		public readonly ?string $altCurrency,
		public readonly bool $supportsDecimals,
	) {}
}
