<?php

declare(strict_types=1);

namespace OCA\Libresign\Enum;

enum PaymentProvider: string
{
	case DPO = 'dpo';
	case DARAJA = 'daraja';

	public function isVerifiable(): bool
	{
		return match ($this) {
			self::DPO => true,
			self::DARAJA => false,
		};
	}

	/**
	 * Helper for DB queries
	 */
	public static function verifiableValues(): array
	{
		return array_map(
			fn(self $p) => $p->value,
			array_filter(self::cases(), fn(self $p) => $p->isVerifiable())
		);
	}
}
