/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { getPrimarySigningAction } from '../../helpers/SigningActionHelper'

describe('getPrimarySigningAction', () => {
	it('prioritizes certificate upload when needed', () => {
		const signMethodsStore = {
			needCertificate: () => true,
			needCreatePassword: () => false,
		}
		const signStore = { errors: [] }

		const result = getPrimarySigningAction(signStore, signMethodsStore, false, false)

		expect(result).toEqual({ action: 'uploadCertificate' })
	})

	it('requires password creation when needed', () => {
		const signMethodsStore = {
			needCertificate: () => false,
			needCreatePassword: () => true,
		}
		const signStore = { errors: [] }

		const result = getPrimarySigningAction(signStore, signMethodsStore, false, false)

		expect(result).toEqual({ action: 'createPassword' })
	})

	it('asks to create signature when required', () => {
		const signMethodsStore = {
			needCertificate: () => false,
			needCreatePassword: () => false,
		}
		const signStore = { errors: [] }

		const result = getPrimarySigningAction(signStore, signMethodsStore, true, false)

		expect(result).toEqual({ action: 'createSignature' })
	})

	it('requests identification documents when required', () => {
		const signMethodsStore = {
			needCertificate: () => false,
			needCreatePassword: () => false,
		}
		const signStore = { errors: [] }

		const result = getPrimarySigningAction(signStore, signMethodsStore, false, true)

		expect(result).toEqual({ action: 'documents' })
	})

	it('returns null when there are signing errors', () => {
		const signMethodsStore = {
			needCertificate: () => false,
			needCreatePassword: () => false,
		}
		const signStore = { errors: ['error'] } as any

		const result = getPrimarySigningAction(signStore, signMethodsStore, false, false)

		expect(result).toBeNull()
	})

	it('returns sign action when all requirements are met', () => {
		const signMethodsStore = {
			needCertificate: () => false,
			needCreatePassword: () => false,
		}
		const signStore = { errors: [] }

		const result = getPrimarySigningAction(signStore, signMethodsStore, false, false)

		expect(result).toEqual({ action: 'sign' })
	})
})
