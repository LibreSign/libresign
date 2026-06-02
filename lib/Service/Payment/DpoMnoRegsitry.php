<?php

namespace OCA\Libresign\Service\Payment;

/**
 * MNO (Mobile Network Operator) Registry
 *
 * Maps ISO region codes → subscriber number prefixes → MNO identifiers.
 *
 * Returns a structured resolution result with a confidence level:
 *
 *   HIGH       → exactly one MNO matched, prefix is not flagged as ambiguous
 *   AMBIGUOUS  → multiple MNOs matched, OR single match on a known ported prefix
 *   UNKNOWN    → region not in registry, OR no prefix matched
 *
 * Confidence contract:
 *   HIGH      → proceed silently, show "Paying via X [Change?]"
 *   AMBIGUOUS → fetch DPO mobile options, show inline selector
 *   UNKNOWN   → fetch DPO mobile options, show inline selector
 *
 * Notes:
 * - Prefix matching is best-effort.
 *   Number portability means HIGH is not a
 *   guarantee — it is a strong signal. We always have to offer a "Change?" affordance.
 * - MNO keys must match DPO's provider strings exactly (lowercase).
 *   We have to verify against GetMobilePaymentOptions per country.
 * - Prefixes are matched against the national subscriber number ONLY
 *   (country code already stripped by the caller via libphonenumber).
 * - Sources: ITU national numbering plans, operator websites, GSMA data.
 *   Last reviewed: 2025. We also have to refresh annually or after issue arises.
 */
class DpoMnoRegistry
{
	/**
	 * Confidence levels returned by resolve().
	 */
	public const CONFIDENCE_HIGH      = 'high';
	public const CONFIDENCE_AMBIGUOUS = 'ambiguous';
	public const CONFIDENCE_UNKNOWN   = 'unknown';

	/**
	 * Resolution result shape:
	 *
	 * [
	 *   'mno'        => string|null,   // e.g. 'mpesa', 'airtel', null
	 *   'confidence' => string,        // HIGH | AMBIGUOUS | UNKNOWN
	 *   'region'     => string,        // ISO 3166-1 alpha-2
	 *   'note'       => string|null,   // human-readable caveat
	 * ]
	 */

	/**
	 * Prefix map.
	 *
	 * Structure per region:
	 * [
	 *   'MNO_KEY' => [
	 *     'prefixes'   => string[],   // regex fragments matched against national number
	 *     'ambiguous'  => bool,       // true if heavy portability or allocation overlap
	 *     'note'       => string,     // why this entry is flagged
	 *   ]
	 * ]
	 *
	 * Regex fragments are anchored at start (^) and matched against the
	 * national subscriber number as a string.
	 */
	private const MAP = [

		// -------------------------------------------------------------------------
		// Kenya (KE) — +254
		// Most stable market in the region. Safaricom dominates.
		// Portability volume is low. Prefix blocks are well-separated.
		// Source: CA Kenya numbering plan (2024)
		// -------------------------------------------------------------------------
		'KE' => [
			'mpesa' => [
				'prefixes'  => [
					'/^70/', '/^71/', '/^72/',
					'/^740/', '/^741/', '/^742/', '/^743/',
					'/^745/', '/^746/', '/^748/',
					'/^757/', '/^758/', '/^759/',
					'/^768/', '/^769/',
					'/^79/',
					'/^110/', '/^111/', '/^112/', '/^113/', '/^114/', '/^115/',
				],
				'ambiguous' => false,
				'note'      => 'Safaricom M-Pesa. Allocation is clear; portability negligible.',
			],
			'airtel' => [
				'prefixes'  => [
					'/^73/',
					'/^750/', '/^751/', '/^752/', '/^753/', '/^754/', '/^755/', '/^756/',
					'/^78/',
					'/^100/', '/^101/', '/^102/',
				],
				'ambiguous' => false,
				'note'      => 'Airtel Kenya. Well-separated from Safaricom blocks.',
			],
			'faiba' => [
				'prefixes'  => ['/^747/'],
				'ambiguous' => false,
				'note'      => 'Faiba 4G (Jamii Telecom). Mobile data SIMs; rare for M-payments.',
			],
		],

		// -------------------------------------------------------------------------
		// Tanzania (TZ) — +255
		// Six active MNOs. Prefix blocks are mostly distinct but Zantel/TTCL
		// allocations are thin and have been partially reassigned over time.
		// Tigo rebranded to MiPawa (2023) — DPO still uses 'tigo' as key,
		// Source: TCRA Tanzania numbering plan (2024)
		// -------------------------------------------------------------------------
		'TZ' => [
			'vodacom' => [
				'prefixes'  => [
					'/^74/', '/^75/', '/^76/',
				],
				'ambiguous' => false,
				'note'      => 'Vodacom Tanzania (M-Pesa). Largest allocation, stable.',
			],
			'airtel' => [
				'prefixes'  => [
					'/^78/', '/^68/', '/^69/',
				],
				'ambiguous' => false,
				'note'      => 'Airtel Tanzania. Distinct blocks from Vodacom.',
			],
			'tigo' => [
				'prefixes'  => [
					'/^71/', '/^65/', '/^67/',
				],
				'ambiguous' => false,
				'note'      => 'Tigo / MiPawa. Rebranded 2023 but numbering unchanged. Confirm DPO key.',
			],
			'halotel' => [
				'prefixes'  => ['/^62/'],
				'ambiguous' => false,
				'note'      => 'Halotel (Viettel Tanzania). Small allocation, stable.',
			],
			'zantel' => [
				'prefixes'  => ['/^77/'],
				'ambiguous' => true,
				'note'      => 'Zantel (Zain → Etisalat → Zanzibar Telecom). Thin allocation; '
					. 'some 077 numbers reassigned to other operators post-merger. '
					. 'Treat as ambiguous and fall back to DPO options.',
			],
			'ttcl' => [
				'prefixes'  => ['/^73/'],
				'ambiguous' => true,
				'note'      => 'TTCL mobile. Very small subscriber base; prefix partially '
					. 'overlaps with historical Airtel allocations. Fall back to DPO options.',
			],
		],

		// -------------------------------------------------------------------------
		// Uganda (UG) — +256
		// Two dominant operators. Prefix blocks are reasonably clean.
		// Africell entered 2014 but has a small allocation (039x).
		// Source: UCC Uganda numbering plan (2024)
		// -------------------------------------------------------------------------
		'UG' => [
			'mtn' => [
				'prefixes'  => [
					'/^77/', '/^78/', '/^76/', '/^39/',
					'/^31/', '/^30/',
				],
				'ambiguous' => false,
				'note'      => 'MTN Uganda Mobile Money. Dominant operator.',
			],
			'airtel' => [
				'prefixes'  => [
					'/^70/', '/^75/', '/^74/', '/^20/',
				],
				'ambiguous' => false,
				'note'      => 'Airtel Uganda. Distinct from MTN blocks.',
			],
			'africell' => [
				'prefixes'  => ['/^79/'],
				'ambiguous' => false,
				'note'      => 'Africell Uganda. Small base; confirm DPO supports this provider.',
			],
		],

		// -------------------------------------------------------------------------
		// Rwanda (RW) — +250
		// Small market, aggressive portability adoption since 2014.
		// MTN and Airtel prefix blocks genuinely overlap on 072/073.
		// Source: RURA Rwanda (2023) — treat most prefixes as best-effort.
		// -------------------------------------------------------------------------
		'RW' => [
			'mtn' => [
				'prefixes'  => [
					'/^78/', '/^79/', '/^72/',
				],
				'ambiguous' => false,
				'note'      => 'MTN Rwanda MoMo. 078/079 are strongly MTN; 072 less so.',
			],
			'airtel' => [
				'prefixes'  => [
					'/^73/', '/^72/',
				],
				'ambiguous' => true,
				'note'      => '073 is primarily Airtel. 072 overlaps with MTN allocation — '
					. 'portability means prefix alone is unreliable here. '
					. 'If matched 072, return AMBIGUOUS.',
			],
		],

		// -------------------------------------------------------------------------
		// Malawi (MW) — +265
		// Two operators: Airtel (dominant) and TNM.
		// Prefix overlap exists on 088 — both carriers have had numbers here.
		// Source: MACRA Malawi (2023)
		// -------------------------------------------------------------------------
		'MW' => [
			'airtel' => [
				'prefixes'  => [
					'/^99/', '/^98/', '/^89/',
					'/^88/', // partial overlap with TNM — flagged below
				],
				'ambiguous' => false,
				'note'      => 'Airtel Malawi. 099/098/089 are unambiguous. '
					. '088 has historical overlap — see TNM note.',
			],
			'tnm' => [
				'prefixes'  => [
					'/^88/', '/^84/',
				],
				'ambiguous' => true,
				'note'      => 'TNM (Telekom Networks Malawi). 088 overlaps with Airtel '
					. 'allocation. If matched exclusively on 088, treat as AMBIGUOUS.',
			],
		],

		// -------------------------------------------------------------------------
		// Zambia (ZM) — +260
		// Three operators. MTN and Airtel have distinct primary blocks.
		// Zamtel is government-owned, small base.
		// Source: ZICTA Zambia (2024)
		// -------------------------------------------------------------------------
		'ZM' => [
			'mtn' => [
				'prefixes'  => [
					'/^96/', '/^76/',
				],
				'ambiguous' => false,
				'note'      => 'MTN Zambia. Clear allocation.',
			],
			'airtel' => [
				'prefixes'  => [
					'/^97/', '/^77/',
				],
				'ambiguous' => false,
				'note'      => 'Airtel Zambia. Distinct from MTN.',
			],
			'zamtel' => [
				'prefixes'  => [
					'/^95/', '/^75/',
				],
				'ambiguous' => false,
				'note'      => 'Zamtel. Government operator, small base. ',
			],
		],

		// -------------------------------------------------------------------------
		// Zimbabwe (ZW) — +263
		// Three mobile money operators. EcoCash (Econet) dominates ~95% market.
		// OneWallet (NetOne) and Telecash (Telecel) are small.
		// Prefix overlap exists — all three carriers have drawn from 077x blocks.
		// Source: POTRAZ Zimbabwe (2023) — treat non-EcoCash as ambiguous.
		// -------------------------------------------------------------------------
		'ZW' => [
			'ecocash' => [
				'prefixes'  => [
					'/^77/', '/^78/', '/^71/', '/^73/',
				],
				'ambiguous' => false,
				'note'      => 'Econet / EcoCash. Dominant by far (~95% mobile money share). '
					. '077 is strongly EcoCash in practice despite formal overlap.',
			],
			'onemoney' => [
				'prefixes'  => [
					'/^71/', '/^78/',
				],
				'ambiguous' => true,
				'note'      => 'NetOne OneWallet. Prefix allocation overlaps with EcoCash. '
					. 'Cannot reliably distinguish from EcoCash on prefix alone.',
			],
			'telecash' => [
				'prefixes'  => [
					'/^73/',
				],
				'ambiguous' => true,
				'note'      => 'Telecel / Telecash. Thin allocation, overlaps with EcoCash 073. '
					. 'Very small subscriber base. Fall back to DPO options.',
			],
		],
	];

	/**
	 * Resolve MNO and confidence from a region code and national subscriber number.
	 *
	 * @param string $region      ISO 3166-1 alpha-2 (e.g. 'KE', 'TZ')
	 * @param string $localNumber National subscriber number, digits only, no country code
	 *                            (e.g. '712345678' for +254712345678)
	 *
	 * @return array{
	 *   mno: string|null,
	 *   confidence: 'high'|'ambiguous'|'unknown',
	 *   region: string,
	 *   note: string|null
	 * }
	 */
	public static function resolve(string $region, string $localNumber): array
	{
		$region = strtoupper($region);
		$localNumber = preg_replace('/\D/', '', $localNumber);

		$regionMap = self::MAP[$region] ?? null;

		if ($regionMap === null) {
			return self::result(null, self::CONFIDENCE_UNKNOWN, $region,
				"Region '{$region}' is not in the registry."
			);
		}

		$matches = [];

		foreach ($regionMap as $mno => $entry) {
			foreach ($entry['prefixes'] as $pattern) {
				if (preg_match($pattern, $localNumber)) {
					$matches[] = [
						'mno'       => $mno,
						'ambiguous' => $entry['ambiguous'],
						'note'      => $entry['note'],
					];
					break; // one match per MNO is enough
				}
			}
		}

		// No prefix matched at all
		if (count($matches) === 0) {
			return self::result(null, self::CONFIDENCE_UNKNOWN, $region,
				"No prefix match found in '{$region}' for number starting with: "
				. substr($localNumber, 0, 5) . '...'
			);
		}

		// Multiple MNOs matched → portability overlap
		if (count($matches) > 1) {
			$mnos = implode(', ', array_column($matches, 'mno'));
			return self::result(null, self::CONFIDENCE_AMBIGUOUS, $region,
				"Prefix matches multiple MNOs in '{$region}': {$mnos}. "
				. 'Number portability likely. Defer to DPO options.'
			);
		}

		// Exactly one match
		$match = $matches[0];

		// Single match but the entry itself is flagged ambiguous
		if ($match['ambiguous']) {
			return self::result($match['mno'], self::CONFIDENCE_AMBIGUOUS, $region,
				$match['note']
			);
		}

		// Clean single match
		return self::result($match['mno'], self::CONFIDENCE_HIGH, $region, null);
	}

	/**
	 * Whether a region is known to the registry at all.
	 * Use this to short-circuit before calling resolve() if needed.
	 */
	public static function supportsRegion(string $region): bool
	{
		return isset(self::MAP[strtoupper($region)]);
	}

	/**
	 * Return all MNOs registered for a region.
	 * Useful for building a manual selector without calling DPO.
	 * Note: DPO options remain authoritative — use this only as a fallback.
	 */
	public static function mnoKeysForRegion(string $region): array
	{
		return array_keys(self::MAP[strtoupper($region)] ?? []);
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	private static function result(
		?string $mno,
		string $confidence,
		string $region,
		?string $note
	): array {
		return [
			'mno'        => $mno,
			'confidence' => $confidence,
			'region'     => $region,
			'note'       => $note,
		];
	}
}
