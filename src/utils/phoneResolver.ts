import {
  parsePhoneNumberFromString,
  AsYouType,
  type CountryCode
} from 'libphonenumber-js'

/**
 * Supported regions in our system
 * Basically DPO & Daraja supported countries
 */
export type SupportedRegion =
  | 'KE' // Kenya
  | 'TZ' // Tanzania
  | 'UG' // Uganda
  | 'RW' // Rwanda
  | 'MW' // Malawi
  | 'ZM' // Zambia
  | 'ZW' // Zimbabwe

/**
 * Payment provider routing
 */
export type PaymentProvider = 'daraja' | 'dpo'

/**
 * Kenyan MNO hint (SOFT detection only)
 */
export type KenyanMno = 'safaricom' | 'airtel' | 'unknown'

/**
 * Resolver result
 */
export interface PhoneResolution {
  isValid: boolean
  e164?: string
  region?: SupportedRegion
  provider?: PaymentProvider
  reason?: string
  mnoHint?: KenyanMno
}

/**
 * Region → Provider mapping
 *
 * IMPORTANT:
 * This is the DEFAULT routing layer.
 *
 * HOWEVER:
 * Kenya (KE) is intentionally overridden below with MNO-based routing.
 *
 * Why:
 * - Only Safaricom numbers should use Daraja (M-Pesa STK)
 * - All other Kenyan numbers (Airtel, etc.) MUST go through DPO
 *
 * This ensures:
 * ✔ correct payment rails
 * ✔ higher success rate
 * ✔ avoids failed STK attempts
 */
const REGION_PROVIDER_MAP: Record<SupportedRegion, PaymentProvider> = {
  KE: 'daraja', // default, overridden below for non-Safaricom numbers
  TZ: 'dpo',
  UG: 'dpo',
  RW: 'dpo',
  MW: 'dpo',
  ZM: 'dpo',
  ZW: 'dpo',
}

/**
 * Normalise phone using libphonenumber (E.164)
 */
export function normalisePhone(
  input: string,
  defaultCountry?: SupportedRegion
): string | null {
  try {
    const parsed = parsePhoneNumberFromString(input, defaultCountry as CountryCode)

    if (!parsed || !parsed.isValid()) return null

    return parsed.number
  } catch {
    return null
  }
}

/**
 * Resolve region + provider from phone
 *
 * 🔥 SINGLE SOURCE OF TRUTH FOR ROUTING 🔥
 *
 * Responsibilities:
 * ✔ validate phone
 * ✔ normalise to E.164
 * ✔ detect region
 * ✔ determine provider (daraja vs dpo)
 * ✔ provide soft MNO hint (Kenya only)
 *
 * MUST REMAIN:
 * - deterministic
 * - side-effect free
 * - backend-aligned
 */
export function resolvePhone(
  input: string,
  defaultCountry: SupportedRegion = 'KE'
): PhoneResolution {
  try {
    const parsed = parsePhoneNumberFromString(input, defaultCountry as CountryCode)

    if (!parsed || !parsed.isValid()) {
      return {
        isValid: false,
        reason: 'invalid_phone',
      }
    }

    const region = parsed.country as SupportedRegion

    if (!region || !(region in REGION_PROVIDER_MAP)) {
      return {
        isValid: false,
        reason: 'unsupported_region',
      }
    }

	console.log(`region`, region)

    const e164 = parsed.number

    /**
     * 🔥 Kenya Special Handling (BUSINESS RULE — DO NOT REMOVE)
     *
     * Routing for Kenya is NOT purely technical — it is driven by a
     * deliberate business decision:
     *
     * - Safaricom numbers → Daraja (M-Pesa STK)
     * - All other Kenyan numbers → DPO
     *
     * Why:
     * - Optimizes success rates for M-Pesa transactions
     * - Aligns with business/payment provider agreements
     * - Ensures best UX for majority Safaricom users
     *
     * ⚠️ IMPORTANT:
     * - This MUST NOT be simplified to "KE → daraja"
     * - This MUST NOT be removed during refactors
     *
     * ⚠️ ALSO IMPORTANT:
     * - MNO detection here is a BEST-EFFORT (prefix-based)
     * - It is NOT a source of truth
     * - Backend remains authoritative and may override routing
     */
    if (region === 'KE') {
      const mno = detectKenyanMno(e164)

      const provider: PaymentProvider =
        mno === 'safaricom' ? 'daraja' : 'dpo'

      return {
        isValid: true,
        e164,
        region,
        provider,
        mnoHint: mno,
      }
    }

    // 🌍 Non-Kenya → standard region mapping
    return {
      isValid: true,
      e164,
      region,
      provider: REGION_PROVIDER_MAP[region],
    }

  } catch {
    return {
      isValid: false,
      reason: 'parse_error',
    }
  }
}

/**
 * Format as user types (UX helper)
 */
export function formatAsYouType(input: string): string {
  return new AsYouType().input(input)
}

/**
 * Detect Kenyan MNO from an E.164 number.
 *
 * Source of truth: Communications Authority of Kenya
 * Numbering Plan — CA/SRM/NNP/MAY/2025 (May 2025)
 * https://ca.go.ke/sites/default/files/CA/Numbering%20Plan/
 *   Telecommunication%20Numbering%20Plan%20for%20Kenya%20May%202025.pdf
 *
 * IMPORTANT:
 * - BEST-EFFORT prefix detection only — not authoritative
 * - Used for client-side routing hint and UX only
 * - Backend remains the source of truth and may override
 *
 * SAFARICOM (M-Pesa) — routes to Daraja
 *   07xx:  700–729, 740–743, 745–746, 748, 757–759, 768–769, 790–799
 *   01xx:  110–117
 *
 * AIRTEL — routes to DPO
 *   07xx:  730–739, 750–756, 762, 780–789
 *   01xx:  100–108
 *
 * UNKNOWN — routes to DPO (Telkom 077x, Faiba 0747,
 *   Equitel/Finserve 0763–0766, and other minor allocations)
 *
 */
export function detectKenyanMno(e164: string): KenyanMno {
  // Strip country code — work on the national subscriber number
  const n = e164.replace('+254', '')

  // SAFARICOM 07xx
  // 700–729  →  0700–0729
  if (/^7[0-2]\d/.test(n)) return 'safaricom'
  // 740–743  →  0740–0743
  if (/^74[0-3]/.test(n)) return 'safaricom'
  // 745–746  →  0745–0746
  if (/^74[5-6]/.test(n)) return 'safaricom'
  // 748      →  0748
  if (/^748/.test(n)) return 'safaricom'
  // 757–759  →  0757–0759  (NOTE: 750–756 is Airtel — order matters)
  if (/^75[7-9]/.test(n)) return 'safaricom'
  // 768–769  →  0768–0769  (NOTE: 762 is Airtel, 763–766 is Finserve)
  if (/^76[8-9]/.test(n)) return 'safaricom'
  // 790–799  →  0790–0799
  if (/^79\d/.test(n)) return 'safaricom'

  // SAFARICOM 01xx
  // 110–117  →  0110–0117
  if (/^11[0-7]/.test(n)) return 'safaricom'

  // AIRTEL 07xx
  // 730–739  →  0730–0739
  if (/^73\d/.test(n)) return 'airtel'
  // 750–756  →  0750–0756
  if (/^75[0-6]/.test(n)) return 'airtel'
  // 762      →  0762
  if (/^762/.test(n)) return 'airtel'
  // 780–789  →  0780–0789
  if (/^78\d/.test(n)) return 'airtel'

  // AIRTEL 01xx
  // 100–108  →  0100–0108
  if (/^10[0-8]/.test(n)) return 'airtel'

  // EVERYTHING ELSE
  // Includes:
  //   Telkom Kenya    → 770–779  (0770–0779)
  //   Finserve/Equitel→ 763–766  (0763–0766)
  //   Jamii/Faiba     → 747      (0747)
  //   Homelands Media → 744      (0744)
  //   Mobile Pay      → 760      (0760)
  //   Eferio          → 761      (0761)
  //   IEBC KIEMS      → 749      (0749)
  //   IoT / M2M misc  → various
  return 'unknown'
}
