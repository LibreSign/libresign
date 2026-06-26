/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

import { realDefinitions } from '../../../../../views/Settings/PolicyWorkbench/settings/realDefinitions'

const expectedTopLevelKeys = [
	'groups_request_sign',
	'identification_documents',
	'identify_methods',
	'signature_flow',
	'envelope_enabled',
	'add_footer',
	'signature_stamp',
	'show_confetti_after_signing',
	'collect_metadata',
	'legal_information',
	'expiry_in_days',
	'maximum_validity',
	'reminder_settings',
	'signature_hash_algorithm',
	'docmdp',
	'tsa_settings',
	'crl_external_validation_enabled',
	'default_user_folder',
	'make_validation_url_private',
	'signing_mode',
] as const

describe('realDefinitions', () => {
	it('registers the expected top-level workbench cards', () => {
		expect(Object.keys(realDefinitions)).toEqual(expectedTopLevelKeys)
	})

	it('keeps each catalog entry aligned with its policy key and category', () => {
		for (const [key, definition] of Object.entries(realDefinitions)) {
			expect(definition.key).toBe(key)
			expect(definition.category).toBeTruthy()
		}
	})

	it('does not expose helper-only or backend-only policies as top-level cards', () => {
		expect(realDefinitions).not.toHaveProperty('approval_group')
		expect(realDefinitions).not.toHaveProperty('renewal_interval')
		expect(realDefinitions).not.toHaveProperty('worker_config')
		expect(realDefinitions).not.toHaveProperty('signature_background_type')
	})

	it('assigns representative categories to representative policies', () => {
		expect(realDefinitions.groups_request_sign.category).toBe('who-can-sign')
		expect(realDefinitions.add_footer.category).toBe('signer-experience')
		expect(realDefinitions.legal_information.category).toBe('what-gets-recorded')
		expect(realDefinitions.docmdp.category).toBe('trust-and-verification')
		expect(realDefinitions.signing_mode.category).toBe('system-behavior')
	})
})
