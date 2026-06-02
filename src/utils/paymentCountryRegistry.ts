/**
 * =========================================================
 * PAYMENT COUNTRY REGISTRY
 * =========================================================
 *
 * Canonical frontend payment geography registry.
 *
 * Responsibilities:
 * - region → display metadata
 * - region → currency context
 * - region → dial code
 * - region → flag
 *
 * IMPORTANT:
 * - Region codes are the canonical keys (ISO-ish)
 * - Backend remains source of truth for routing/business logic
 * - Frontend uses this for UX/presentation only
 *
 * Used by:
 * - PaymentRouteSummary
 * - phone formatting UX
 * - FX display
 * - payment recovery UI
 * - future localization
 */

export interface PaymentCountryMeta {
	/**
	 * Canonical region code
	 * Example: KE, TZ, UG
	 */
	region: string

	/**
	 * Human-readable lowercase country label
	 * Used for display/UI only
	 */
	country: string

	/**
	 * Emoji flag
	 */
	flag: string

	/**
	 * International dial code
	 */
	dialCode: string

	/**
	 * Primary settlement/display currency
	 */
	currency: string

	/**
	 * Optional alternate currency
	 * Example: Zimbabwe dual currency support
	 */
	altCurrency?: string | null

	/**
	 * Whether currency commonly supports decimals
	 */
	supportsDecimals: boolean
}

export const PAYMENT_COUNTRY_REGISTRY: Record<string, PaymentCountryMeta> = {
	KE: {
		region: 'KE',
		country: 'Kenya',
		flag: '🇰🇪',
		dialCode: '+254',
		currency: 'KES',
		altCurrency: null,
		supportsDecimals: false,
	},

	TZ: {
		region: 'TZ',
		country: 'Tanzania',
		flag: '🇹🇿',
		dialCode: '+255',
		currency: 'TZS',
		altCurrency: null,
		supportsDecimals: false,
	},

	ZNZ: {
		region: 'ZNZ',
		country: 'Zanzibar',
		flag: '🇹🇿',
		dialCode: '+255',
		currency: 'TZS',
		altCurrency: null,
		supportsDecimals: false,
	},

	UG: {
		region: 'UG',
		country: 'Uganda',
		flag: '🇺🇬',
		dialCode: '+256',
		currency: 'UGX',
		altCurrency: null,
		supportsDecimals: false,
	},

	RW: {
		region: 'RW',
		country: 'Rwanda',
		flag: '🇷🇼',
		dialCode: '+250',
		currency: 'RWF',
		altCurrency: null,
		supportsDecimals: false,
	},

	GH: {
		region: 'GH',
		country: 'Ghana',
		flag: '🇬🇭',
		dialCode: '+233',
		currency: 'GHS',
		altCurrency: null,
		supportsDecimals: true,
	},

	CI: {
		region: 'CI',
		country: 'Ivory Coast',
		flag: '🇨🇮',
		dialCode: '+225',
		currency: 'XOF',
		altCurrency: null,
		supportsDecimals: false,
	},

	MW: {
		region: 'MW',
		country: 'Malawi',
		flag: '🇲🇼',
		dialCode: '+265',
		currency: 'MWK',
		altCurrency: null,
		supportsDecimals: true,
	},

	ZM: {
		region: 'ZM',
		country: 'Zambia',
		flag: '🇿🇲',
		dialCode: '+260',
		currency: 'ZMW',
		altCurrency: null,
		supportsDecimals: true,
	},

	ZW: {
		region: 'ZW',
		country: 'Zimbabwe',
		flag: '🇿🇼',
		dialCode: '+263',
		currency: 'ZWL',
		altCurrency: 'USD',
		supportsDecimals: true,
	},
}

/**
 * Safe helper
 */
export function getPaymentCountryMeta(region?: string | null): PaymentCountryMeta | null {
	if (!region) return null

	return PAYMENT_COUNTRY_REGISTRY[
		region.toUpperCase()
	] ?? null
}
