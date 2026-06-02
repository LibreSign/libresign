<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\DTO;

final class FxEngineResultDTO
{
	public function __construct(
		public readonly int $displayAmount,      // minor units
		public readonly string $displayCurrency, // ISO code
		public readonly float $fxRate,           // applied rate
		public readonly string $fxRateSource,          // e.g. exchangerate.host
		public readonly \DateTimeImmutable $fxRateLockedAt         // UTC timestamp
	) {}
}
