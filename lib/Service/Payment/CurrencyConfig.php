<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment;

final class CurrencyConfig
{
	public const SUPPORTED_CURRENCIES = [
		'KES' => ['decimals' => 2, 'countries' => ['KE']],
		'TZS' => ['decimals' => 0, 'countries' => ['TZ']],
		'UGX' => ['decimals' => 0, 'countries' => ['UG']],
		'RWF' => ['decimals' => 0, 'countries' => ['RW']],
		'MWK' => ['decimals' => 2, 'countries' => ['MW']],
		'ZMW' => ['decimals' => 2, 'countries' => ['ZM']],
		'ZWL' => ['decimals' => 2, 'countries' => ['ZW']],
	];

	public static function decimals(string $currency): int
	{
		return self::SUPPORTED_CURRENCIES[$currency]['decimals'] ?? 2;
	}
}
