<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment\DTO;

use InvalidArgumentException;

final class PaymentPhoneResolutionDTO
{
	public function __construct(
		public readonly bool $valid,
		public readonly ?string $e164,
		public readonly ?string $e164Digits,
		public readonly ?string $national,
		public readonly ?string $region,
		public readonly ?string $carrierHint,
		public readonly ?string $countryCallingCode,
	) {
		if (!$e164Digits && !$national) {
			throw new InvalidArgumentException('At least one of e164Digits or national must be provided');
		}
	}

	public function toArray(): array
	{
		return [
			'valid' => $this->valid,
			'e164' => $this->e164,
			'e164Digits' => $this->e164Digits,
			'national' => $this->national,
			'region' => $this->region,
			'carrierHint' => $this->carrierHint,
			'countryCallingCode' => $this->countryCallingCode,
		];
	}
}
