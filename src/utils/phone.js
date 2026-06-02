/**
 * Normalize Kenyan phone numbers to E.164 format
 * Output example: +254712345678
 */

export const sanitisePhoneNumber = (phoneNumber) => {
	if (!phoneNumber) return ''

	// remove spaces, brackets, dashes etc
	let normalized = phoneNumber.replace(/\D/g, '')

	// 0712345678 → +254712345678
	if (normalized.startsWith('0') && normalized.length === 10) {
		return '+254' + normalized.slice(1)
	}

	// 712345678 → +254712345678
	if (normalized.length === 9 && normalized.startsWith('7')) {
		return '+254' + normalized
	}

	// 254712345678 → +254712345678
	if (normalized.startsWith('254') && normalized.length === 12) {
		return '+' + normalized
	}

	// already +254712345678
	if (phoneNumber.startsWith('+254') && normalized.length === 12) {
		return '+254' + normalized.slice(3)
	}

	return ''
}
