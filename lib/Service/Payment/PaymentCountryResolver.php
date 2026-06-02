<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment;

use OCA\Libresign\Service\Payment\DTO\PaymentCountryContextDTO;

final class PaymentCountryResolver
{
	private const MAP = [
		'KE' => [
			'country'          => 'kenya',
			'currency'         => 'KES',
			'altCurrency'      => null,
			'supportsDecimals' => false,
		],

		'TZ' => [
			'country'          => 'tanzania',
			'currency'         => 'TZS',
			'altCurrency'      => null,
			'supportsDecimals' => false,
		],

		'ZNZ' => [
			'country'          => 'zanzibar',
			'currency'         => 'TZS',
			'altCurrency'      => null,
			'supportsDecimals' => false,
		],

		'UG' => [
			'country'          => 'uganda',
			'currency'         => 'UGX',
			'altCurrency'      => null,
			'supportsDecimals' => false,
		],

		'RW' => [
			'country'          => 'rwanda',
			'currency'         => 'RWF',
			'altCurrency'      => null,
			'supportsDecimals' => false,
		],

		'GH' => [
			'country'          => 'ghana',
			'currency'         => 'GHS',
			'altCurrency'      => null,
			'supportsDecimals' => true,
		],

		'CI' => [
			'country'          => 'ivory coast',
			'currency'         => 'XOF',
			'altCurrency'      => null,
			'supportsDecimals' => false,
		],

		'MW' => [
			'country'          => 'malawi',
			'currency'         => 'MWK',
			'altCurrency'      => null,
			'supportsDecimals' => true,
		],

		'ZM' => [
			'country'          => 'zambia',
			'currency'         => 'ZMW',
			'altCurrency'      => null,
			'supportsDecimals' => true,
		],

		'ZW' => [
			'country'          => 'zimbabwe',
			'currency'         => 'ZWL',
			'altCurrency'      => 'USD', // dual currency system
			'supportsDecimals' => true,
		],
	];

	public function resolve(?string $region): ?PaymentCountryContextDTO
	{
		if (!$region || $region === '') {
			return null;
		}

		$key = strtoupper($region);

		if (!isset(self::MAP[$key])) {
			return null;
		}

		$data = self::MAP[$key];

		return new PaymentCountryContextDTO(
			region: $key,
			country: $data['country'],
			currency: $data['currency'],
			altCurrency: $data['altCurrency'],
			supportsDecimals: $data['supportsDecimals'],
		);
	}
}
