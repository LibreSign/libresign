<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Payment;

use OCA\Libresign\Enum\PaymentCapability;
use OCA\Libresign\Enum\PaymentFlowMode;
use OCA\Libresign\Enum\PaymentProvider;
use OCA\Libresign\Enum\ResolutionConfidence;
use OCA\Libresign\Service\Payment\DTO\MnoRoutingResultDTO;

/**
 *
 * MNO ROUTING REGISTRY
 *
 * Single source of truth for:
 *   - Which capability handles a given carrier  (mobile_money | card)
 *   - Which provider is preferred              (daraja | dpo)
 *   - DPO MNO key                              (exact string DPO expects in ChargeTokenMobile)
 *   - Transaction limits enforced by DPO/telco (min, max, currency, decimals)
 *   - Payment mode                             (stk_push | instructions | both)
 *
 * RESPONSIBILITIES
 *   This class answers: given a confirmed carrier + region,
 *   which service capability handles it, which provider is preferred,
 *   and what constraints apply?
 *
 *   It does NOT:
 *   - detect MNO from a raw phone number   → DpoMnoRegistry
 *   - parse or validate phone numbers      → PhoneResolutionService
 *   - call DPO or Daraja APIs             → Provider adapters
 *
 */
class MnoRoutingRegistry
{
	// ROUTING TABLE

	// Keyed by ISO 3166-1 alpha-2 region code.
	// Each entry is a list of carrier → route mappings.

	// Fields per route:

	//    match          string[]   Lowercase substrings matched against
	//                              the normalised carrier name from
	//                              PhoneResolutionService.
	//                              First match wins (order matters).

	//    dpoMnoKey      string     Exact MNO string DPO expects in
	//                              ChargeTokenMobile <MNO> field.
	//                              Verify against GetMobilePaymentOptions.

	//    capability     string     PaymentCapability

	//    preferredProvider string  PaymentProvider

	//    mode           string     PaymentFlowMode

	//    currency       string     ISO 4217 currency code

	//    minAmount      float      Minimum transaction amount (in currency units)

	//    maxAmount      float      Maximum transaction amount (in currency units)
	//                              NOTE: telco tier limits may be lower — this is
	//                              the DPO-documented ceiling only.

	//    supportsDecimals bool     Whether the currency/MNO pair accepts decimal amounts

	//    notes          string     Caveats, portability flags, or operational notes.


	private const ROUTES = [

		// Kenya (KE) — KES — +254
		//
		// Two DPO-supported MNOs: Mpesa and Airtel.
		// Safaricom/Mpesa is routed via Daraja (STK push native).
		// Airtel Kenya goes through DPO.
		// No decimal support in KES.
		// Source: DPO MNO Advisory Feb 2025 + Daraja integration.
		'KE' => [
			[
				'match'            => ['safaricom', 'mpesa', 'm-pesa'],
				'dpoMnoKey'        => 'Mpesa',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::STK_PUSH,
				'currency'         => 'KES',
				'minAmount'        => 1,
				'maxAmount'        => 250000,
				'supportsDecimals' => false,
			],
			[
				'match'            => ['airtel'],
				'dpoMnoKey'        => 'Airtel',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'KES',
				'minAmount'        => 10,
				'maxAmount'        => 250000,
				'supportsDecimals' => false,
			],
		],

		// Tanzania (TZ) — TZS — +255
		//
		// Six DPO-supported MNOs. All share the same TZS limits.
		// Min 200 TZS required for STK push.
		// Zantel and TTCL are flagged ambiguous in DpoMnoRegistry
		// due to prefix overlap — confidence passed in from upstream.
		// Source: DPO Advisory Feb 2025.
		//
		'TZ' => [
			[
				'match'            => ['vodacom'],
				'dpoMnoKey'        => 'Vodacom',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'TZS',
				'minAmount'        => 200,
				'maxAmount'        => 3000000,
				'supportsDecimals' => false,
			],
			[
				'match'            => ['airtel'],
				'dpoMnoKey'        => 'Airtel',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'TZS',
				'minAmount'        => 200,
				'maxAmount'        => 3000000,
				'supportsDecimals' => false,
			],
			[
				'match'            => ['tigo', 'mipawa', 'mi-pawa'],
				'dpoMnoKey'        => 'Tigo',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'TZS',
				'minAmount'        => 200,
				'maxAmount'        => 3000000,
				'supportsDecimals' => false,
			],
			[
				'match'            => ['halotel', 'viettel'],
				'dpoMnoKey'        => 'Halotel',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'TZS',
				'minAmount'        => 200,
				'maxAmount'        => 3000000,
				'supportsDecimals' => false,
			],
			[
				'match'            => ['zantel'],
				'dpoMnoKey'        => 'Zantel',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'TZS',
				'minAmount'        => 200,
				'maxAmount'        => 3000000,
				'supportsDecimals' => false,
			],
			[
				'match'            => ['ttcl'],
				'dpoMnoKey'        => 'TTCL',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'TZS',
				'minAmount'        => 200,
				'maxAmount'        => 3000000,
				'supportsDecimals' => false,
			],
		],


		// Zanzibar (ZNZ) — TZS — treated as separate DPO region
		//
		// DPO advisory lists "Zanzibar" separately with MNO "Zntigo".
		// Uses TZS and shares the same limits as Tanzania.
		// ISO 3166-1 does not have a code for Zanzibar — DPO uses
		// the country name. Callers should normalise as needed.

		'ZNZ' => [
			[
				'match'            => ['zntigo', 'zanzibar', 'tigo'],
				'dpoMnoKey'        => 'Zntigo',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'TZS',
				'minAmount'        => 200,
				'maxAmount'        => 3000000,
				'supportsDecimals' => false,
			],
		],

		// Uganda (UG) — UGX — +256
		//
		// Two DPO-supported MNOs: MTN and Airtel.
		// High max limit (5M UGX) but depends on telco tier level.
		// Source: DPO Advisory Feb 2025.

		'UG' => [
			[
				'match'            => ['mtn'],
				'dpoMnoKey'        => 'MTN',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'UGX',
				'minAmount'        => 500,
				'maxAmount'        => 5000000,
				'supportsDecimals' => false,
			],
			[
				'match'            => ['airtel'],
				'dpoMnoKey'        => 'Airtel',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'UGX',
				'minAmount'        => 500,
				'maxAmount'        => 5000000,
				'supportsDecimals' => false,
			],
		],


		// Rwanda (RW) — RWF — +250
		//
		// Two DPO-supported MNOs: MTN and Airtel.
		// Prefix overlap on 072 flagged in DpoMnoRegistry.
		// DPO advisory has a typo: "2,0000,000" — treating as 2,000,000.
		// Source: DPO Advisory Feb 2025.
		'RW' => [
			[
				'match'            => ['mtn'],
				'dpoMnoKey'        => 'MTN',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'RWF',
				'minAmount'        => 10,
				'maxAmount'        => 2000000,
				'supportsDecimals' => false,
			],
			[
				'match'            => ['airtel'],
				'dpoMnoKey'        => 'Airtel',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'RWF',
				'minAmount'        => 10,
				'maxAmount'        => 2000000,
				'supportsDecimals' => false,
			],
		],

		// Ghana (GH) — GHS — +233
		//
		// Two DPO-supported MNOs: Vodacom and MTN.
		// Note: DPO advisory lists "Vodacom" for Ghana — this is likely
		// Vodafone Ghana (not Vodacom Tanzania). DPO key is "Vodacom".
		// GHS supports decimals.
		// Source: DPO Advisory Feb 2025.
		'GH' => [
			[
				'match'            => ['mtn'],
				'dpoMnoKey'        => 'MTN',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'GHS',
				'minAmount'        => 0.1,
				'maxAmount'        => 15000,
				'supportsDecimals' => true,
			],
			[
				'match'            => ['vodacom', 'vodafone'],
				'dpoMnoKey'        => 'Vodacom',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'GHS',
				'minAmount'        => 0.1,
				'maxAmount'        => 10000,
				'supportsDecimals' => true,
			],
			[
				'match'            => ['airtel', 'tigo', 'airteltigo'],
				'dpoMnoKey'        => null, // Not in DPO advisory
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::INSTRUCTIONS,
				'currency'         => 'GHS',
				'minAmount'        => 0.1,
				'maxAmount'        => 10000,
				'supportsDecimals' => true,
			],
		],

		// Ivory Coast / Côte d'Ivoire (CI) — XOF — +225
		//
		// Two DPO-supported MNOs: MTN and Orange.
		// XOF (West African CFA franc) does not support decimals.
		// Source: DPO Advisory Feb 2025.
		'CI' => [
			[
				'match'            => ['mtn'],
				'dpoMnoKey'        => 'MTN',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'XOF',
				'minAmount'        => 100,
				'maxAmount'        => 2000000,
				'supportsDecimals' => false,
			],
			[
				'match'            => ['orange'],
				'dpoMnoKey'        => 'Orange',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'XOF',
				'minAmount'        => 100,
				'maxAmount'        => 2000000,
				'supportsDecimals' => false,
			],
		],

		// Malawi (MW) — MWK — +265
		//
		// One DPO-supported MNO: Airtel.
		// TNM is present in the market but NOT listed in DPO advisory.
		// MWK supports decimals per DPO advisory.
		// Source: DPO Advisory Feb 2025 + DpoMnoRegistry.
		'MW' => [
			[
				'match'            => ['airtel'],
				'dpoMnoKey'        => 'Airtel',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'MWK',
				'minAmount'        => 50,
				'maxAmount'        => 350000,
				'supportsDecimals' => true,
			],
			[
				'match'            => ['tnm', 'telekom networks'],
				'dpoMnoKey'        => null, // Not in DPO advisory
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::INSTRUCTIONS,
				'currency'         => 'MWK',
				'minAmount'        => 50,
				'maxAmount'        => 350000,
				'supportsDecimals' => true,
			],
		],

		// Zambia (ZM) — ZMW — +260
		//
		// Two DPO-supported MNOs: MTN and Airtel.
		// Zamtel is in the market but NOT listed in DPO advisory.
		// ZMW supports decimals.
		// Source: DPO Advisory Feb 2025 + DpoMnoRegistry.
		'ZM' => [
			[
				'match'            => ['mtn'],
				'dpoMnoKey'        => 'MTN',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'ZMW',
				'minAmount'        => 0.1,
				'maxAmount'        => 20000,
				'supportsDecimals' => true,
			],
			[
				'match'            => ['airtel'],
				'dpoMnoKey'        => 'Airtel',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'ZMW',
				'minAmount'        => 1,
				'maxAmount'        => 10000,
				'supportsDecimals' => true,
			],
			[
				'match'            => ['zamtel'],
				'dpoMnoKey'        => null, // Not in DPO advisory
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::INSTRUCTIONS,
				'currency'         => 'ZMW',
				'minAmount'        => 1,
				'maxAmount'        => 10000,
				'supportsDecimals' => true,
			],
		],

		// Zimbabwe (ZW) — ZWL / USD — +263
		//
		// One DPO-supported MNO: EcoCash (Econet).
		// Dual currency: ZWL and USD both supported by EcoCash.
		// OneWallet and Telecash not in DPO advisory.
		// Source: DPO Advisory Feb 2025 + DpoMnoRegistry.
		'ZW' => [
			[
				'match'            => ['ecocash', 'econet'],
				'dpoMnoKey'        => 'EcoCash',
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::BOTH,
				'currency'         => 'ZWL', // primary; USD also supported
				'altCurrency'      => 'USD',
				'minAmount'        => 0.1,
				'maxAmount'        => 1000000,
				'supportsDecimals' => true,
			],
			[
				'match'            => ['onemoney', 'one money', 'netone'],
				'dpoMnoKey'        => null, // Not in DPO advisory
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::INSTRUCTIONS,
				'currency'         => 'ZWL',
				'minAmount'        => 0.1,
				'maxAmount'        => 1000000,
				'supportsDecimals' => true,
			],
			[
				'match'            => ['telecash', 'telecel'],
				'dpoMnoKey'        => null, // Not in DPO advisory
				'capability'       => PaymentCapability::MOBILE_MONEY,
				'preferredProvider'=> PaymentProvider::DPO,
				'mode'             => PaymentFlowMode::INSTRUCTIONS,
				'currency'         => 'ZWL',
				'minAmount'        => 0.1,
				'maxAmount'        => 1000000,
				'supportsDecimals' => true,
			],
		],

	];

	private const FORCE_AMBIGUOUS_TESTING = true;

	/**
	 * Route a carrier to a capability + provider decision.
	 *
	 * @param string|null $region     ISO 3166-1 alpha-2 (e.g. 'KE', 'TZ')
	 *                                or 'ZNZ' for Zanzibar.
	 * @param string|null $carrier    Carrier name from PhoneResolutionService or MnoDetectionRegistry
	 *                                mno key. Will be normalised to lowercase internally.
	 * @param ResolutionConfidence    $confidence 'high' | 'ambiguous' | 'unknown'
	 *                                Passed in from MnoDetectionRegistry or PhoneResolutionService.
	 *                                This class NEVER overrides a lower confidence —
	 *                                it may only degrade it (e.g. null dpoMnoKey).
	 *
	 * @return MnoRoutingResultDTO
	 */
	public function route(
		PaymentCapability $capability,
		?string $country,
		?string $region,
		?string $carrier,
		ResolutionConfidence $confidence = ResolutionConfidence::UNKNOWN
	): MnoRoutingResultDTO {

		if ($capability === PaymentCapability::CARD) {

			return new MnoRoutingResultDTO(
				capability: PaymentCapability::CARD,
				preferredProvider: PaymentProvider::DPO,
				dpoMnoKey: null,
				mode: PaymentFlowMode::INSTRUCTIONS,
				currency: null,
				altCurrency: null,
				minAmount: null,
				maxAmount: null,
				supportsDecimals: true,
				confidence: ResolutionConfidence::HIGH,
				notes: 'Card flow bypasses MNO routing',
				country: $country,
				region: $region,
			);
		}

		$forceAmbiguous =
			self::FORCE_AMBIGUOUS_TESTING &&
			$region === 'KE' &&
			$carrier !== null &&
			str_contains($carrier, 'mpesa') &&
			$capability === PaymentCapability::MOBILE_MONEY;

		$region  = $region  ? strtoupper(trim($region))  : null;
		$carrier = $carrier ? strtolower(trim($carrier)) : null;

		// No region
		if ($region === null) {
			return $this->build(
				PaymentCapability::MOBILE_MONEY,
				PaymentProvider::DPO,
				null,
				PaymentFlowMode::INSTRUCTIONS,
				null, null, null, null, null,
				ResolutionConfidence::UNKNOWN,
				'No region provided',
				$country,
				$region,
			);
		}

		// Unsupported region
		if (!isset(self::ROUTES[$region])) {
			return $this->build(
				PaymentCapability::MOBILE_MONEY,
				PaymentProvider::DPO,
				null,
				PaymentFlowMode::INSTRUCTIONS,
				null, null, null, null, null,
				ResolutionConfidence::UNKNOWN,
				"Unsupported region {$region}",
				$country,
				$region,
			);
		}

		// No carrier
		if ($carrier === null) {
			return $this->build(
				PaymentCapability::MOBILE_MONEY,
				PaymentProvider::DPO,
				null,
				PaymentFlowMode::INSTRUCTIONS,
				null, null, null, null, null,
				ResolutionConfidence::AMBIGUOUS,
				'Carrier missing',
				$country,
				$region,
			);
		}

		// Match carrier
		foreach (self::ROUTES[$region] as $route) {
			foreach ($route['match'] as $fragment) {
				if (str_contains($carrier, $fragment)) {

					$resolvedConfidence = $forceAmbiguous
						? ResolutionConfidence::AMBIGUOUS
						: (
							$route['dpoMnoKey'] === null
							? ResolutionConfidence::AMBIGUOUS
							: $confidence
						);

					return $this->build(
						$route['capability'],
						$route['preferredProvider'],
						$route['dpoMnoKey'],
						$route['mode'],
						$route['currency'] ?? null,
						$route['altCurrency'] ?? null,
						$route['minAmount'] ?? null,
						$route['maxAmount'] ?? null,
						$route['supportsDecimals'] ?? null,
						$resolvedConfidence,
						$route['notes'] ?? null,
						$country,
						$region,
					);
				}
			}
		}

		// No match
		return $this->build(
			PaymentCapability::MOBILE_MONEY,
			PaymentProvider::DPO,
			null,
			PaymentFlowMode::INSTRUCTIONS,
			null, null, null, null, null,
			ResolutionConfidence::AMBIGUOUS,
			"Carrier {$carrier} not matched in {$region}",
			$country,
			$region,
		);
	}

	public function supportsRegion(string $region): bool
	{
		return isset(self::ROUTES[strtoupper($region)]);
	}

	public function supportedRegions(): array
	{
		return array_keys(self::ROUTES);
	}

	/**
	 * Validate amount using DTO
	 */
	public function validateAmount(MnoRoutingResultDTO $route, float $amount): array
	{
		try {
			$route->validateAmount($amount);
			return ['valid' => true, 'reason' => null];
		} catch (\InvalidArgumentException $e) {
			return ['valid' => false, 'reason' => $e->getMessage()];
		}
	}

	/**
	 * Build DTO
	 */
	private function build(
		PaymentCapability $capability,
		PaymentProvider $provider,
		?string $mno,
		PaymentFlowMode $mode,
		?string $currency,
		?string $altCurrency,
		?float $min,
		?float $max,
		?bool $decimals,
		ResolutionConfidence $confidence,
		?string $notes,
		?string $country,
		?string $region,
	): MnoRoutingResultDTO {
		return new MnoRoutingResultDTO(
			$capability,
			$provider,
			$mno,
			$mode,
			$currency,
			$altCurrency,
			$min,
			$max,
			$decimals,
			$confidence,
			$notes,
			$country,
			$region,
		);
	}
}
