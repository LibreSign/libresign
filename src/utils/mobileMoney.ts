/**
 * Normalize Kenyan phone numbers to +254XXXXXXXXX format
 */

import type { SupportedRegion } from '@/payment'

const sanitisePhoneNumber = (phoneNumber: string) => {

	if (!phoneNumber) return ''

	// remove spaces, dashes, brackets etc
	let normalized = phoneNumber.replace(/\D/g, '')

	// 0712345678 → +254712345678
	if (normalized.startsWith('0')) {
		normalized = '254' + normalized.slice(1)
	}

	// 712345678 → +254712345678
	if (normalized.length === 9 && normalized.startsWith('7')) {
		normalized = '254' + normalized
	}

	// ensure + prefix
	if (!normalized.startsWith('254')) {
		return ''
	}

	return '+' + normalized
}


/**
 * Detect Kenyan mobile provider
 */

const detectProvider = (phoneNumber: string) => {
    const sanitized = sanitisePhoneNumber(phoneNumber)
    if (!sanitized) return null

    // Remove + and ensure we are working with 254...
    const normalized = sanitized.replace('+', '')

    /**
     * SAFARICOM (M-PESA)
     * Includes classic 07xx and newer 011x series.
     * Prefixes: 70, 71, 72, 740-743, 745-746, 748, 757-759, 768-769, 79, 110-115
     */
    if (/^(254)(7(0|1|2|4[0-3,5-6,8]|5[7-9]|6[8-9]|9)|11[0-5])/.test(normalized)) {
        return 'SAFARICOM'
    }

    /**
     * AIRTEL (Airtel Money)
     * Includes classic 07xx and newer 010x series.
     * Prefixes: 73, 750-756, 78, 100-102
     */
    if (/^(254)(7(3|5[0-6]|8)|10[0-2])/.test(normalized)) {
        return 'AIRTEL'
    }

    return null
}

const isValidKenyanNumber = (phone: string) => {
	return /^\+2547\d{8}$/.test(phone)
}

const normaliseRegion = (input?: string | null): SupportedRegion | null => {
  if (!input) return null

  const value = input.toLowerCase().trim()

  const map: Record<string, SupportedRegion> = {
    // ISO
    ke: 'KE',
    tz: 'TZ',
    ug: 'UG',
    rw: 'RW',
    mw: 'MW',
    zm: 'ZM',
    zw: 'ZW',

    // Full names
    kenya: 'KE',
    tanzania: 'TZ',
    uganda: 'UG',
    rwanda: 'RW',
    malawi: 'MW',
    zambia: 'ZM',
    zimbabwe: 'ZW',
  }

  return map[value] ?? null
}

export {
	sanitisePhoneNumber,
	detectProvider,
	isValidKenyanNumber,
	normaliseRegion,
}
