/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, describe, expect, it, beforeEach, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import JSConfetti from 'js-confetti'
import Validation from '../../views/Validation.vue'

// Mock js-confetti
vi.mock('js-confetti', () => ({
	default: vi.fn(),
}))

// Mock @nextcloud packages
vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
		put: vi.fn(),
		delete: vi.fn(),
	},
}))

vi.mock('@nextcloud/files', () => ({
	formatFileSize: vi.fn((size) => `${size} B`),
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => ({
		uid: 'test-user',
		displayName: 'Test User',
	})),
	getRequestToken: vi.fn(() => 'test-csrf-token'),
}))

vi.mock('@nextcloud/logger', () => ({
	getLogger: vi.fn(() => ({
		error: vi.fn(),
		warn: vi.fn(),
		info: vi.fn(),
		debug: vi.fn(),
	})),
	getLoggerBuilder: vi.fn(() => ({
		setApp: vi.fn().mockReturnThis(),
		detectUser: vi.fn().mockReturnThis(),
		build: vi.fn(() => ({
			error: vi.fn(),
			warn: vi.fn(),
			info: vi.fn(),
			debug: vi.fn(),
		})),
	})),
}))

vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn((app: string, text: string, vars?: Record<string, string>) => {
		if (vars) {
			return text.replace(/{(\w+)}/g, (_m: string, key: string) => vars[key as keyof typeof vars] || key)
		}
		return text
	}),
	translatePlural: vi.fn((app: string, singular: string, plural: string, count: number, vars?: Record<string, string>) => {
		const template = count === 1 ? singular : plural
		if (vars) {
			return template.replace(/{(\w+)}/g, (_m: string, key: string) => vars[key as keyof typeof vars] || key)
		}
		return template
	}),
	t: vi.fn((app: string, text: string, vars?: Record<string, string>) => {
		if (vars) {
			return text.replace(/{(\w+)}/g, (_m: string, key: string) => vars[key as keyof typeof vars] || key)
		}
		return text
	}),
	n: vi.fn((app: string, singular: string, plural: string, count: number, vars?: Record<string, string>) => {
		const template = count === 1 ? singular : plural
		if (vars) {
			return template.replace(/{(\w+)}/g, (_m: string, key: string) => vars[key as keyof typeof vars] || key)
		}
		return template
	}),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

// Mock router
const mockRoute = {
	params: {},
	query: {},
}

const mockRouter = {
	push: vi.fn(),
}

// Mock initial state
vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => defaultValue),
}))

// Mock router module
vi.mock('@nextcloud/router', () => ({
	generateUrl: vi.fn((path) => path),
	generateOcsUrl: vi.fn((path) => path),
}))

// Mock stores
vi.mock('../../store/sign.js', () => ({
	useSignStore: vi.fn(() => ({
		document: {},
	})),
}))

vi.mock('../../store/sidebar.js', () => ({
	useSidebarStore: vi.fn(() => ({
		hideSidebar: vi.fn(),
	})),
}))

// Mock utils
vi.mock('../../utils/viewer.js', () => ({
	openDocument: vi.fn(),
}))

vi.mock('../../utils/fileStatus.js', () => ({
	getStatusLabel: vi.fn((status) => `Status: ${status}`),
}))

describe('Validation.vue - Business Logic', () => {
	let wrapper!: ReturnType<typeof shallowMount>
	let mockAddConfetti: ReturnType<typeof vi.fn>

	beforeEach(() => {
		mockAddConfetti = vi.fn()
		// Must use `function` syntax so vitest accepts it as a valid constructor mock
		vi.mocked(JSConfetti).mockImplementation(function() {
			return { addConfetti: mockAddConfetti }
		} as unknown as typeof JSConfetti)

		wrapper = shallowMount(Validation, {
			mocks: {
				$route: mockRoute,
				$router: mockRouter,
			},
			stubs: {
				NcActionButton: true,
				NcActions: true,
				NcAvatar: true,
				NcButton: true,
				NcDialog: true,
				NcIconSvgWrapper: true,
				NcListItem: true,
				NcLoadingIcon: true,
				NcNoteCard: true,
				NcRichText: true,
				NcTextField: true,
			},
		})
	})

	describe('canValidate computed property', () => {
		it('returns false when uuidToValidate is empty', () => {
			wrapper.setData({ uuidToValidate: '' })
			expect(wrapper.vm.canValidate).toBe(false)
		})

		it('accepts numeric IDs', () => {
			wrapper.setData({ uuidToValidate: '12345' })
			expect(wrapper.vm.canValidate).toBe(true)
		})

		it('accepts valid UUID format', () => {
			wrapper.setData({ uuidToValidate: '550e8400-e29b-41d4-a716-446655440000' })
			expect(wrapper.vm.canValidate).toBe(true)
		})

		it('rejects invalid UUID format', () => {
			wrapper.setData({ uuidToValidate: 'invalid-uuid-format' })
			expect(wrapper.vm.canValidate).toBe(false)
		})

		it('rejects UUID with wrong version (not v4)', () => {
			wrapper.setData({ uuidToValidate: '550e8400-e29b-31d4-a716-446655440000' })
			expect(wrapper.vm.canValidate).toBe(false)
		})

		it('rejects UUID with wrong variant', () => {
			wrapper.setData({ uuidToValidate: '550e8400-e29b-41d4-1716-446655440000' })
			expect(wrapper.vm.canValidate).toBe(false)
		})
	})

	describe('helperTextValidation computed property', () => {
		it('shows error message for invalid UUID', () => {
			wrapper.setData({ uuidToValidate: 'invalid' })
			expect(wrapper.vm.helperTextValidation).toBe('Invalid UUID')
		})

		it('returns empty string for valid UUID', () => {
			wrapper.setData({ uuidToValidate: '550e8400-e29b-41d4-a716-446655440000' })
			expect(wrapper.vm.helperTextValidation).toBe('')
		})

		it('returns empty string when uuidToValidate is empty', () => {
			wrapper.setData({ uuidToValidate: '' })
			expect(wrapper.vm.helperTextValidation).toBe('')
		})
	})

	describe('isEnvelope computed property', () => {
		it('returns true when nodeType is envelope', () => {
			wrapper.setData({
				document: { nodeType: 'envelope' },
			})
			expect(wrapper.vm.isEnvelope).toBe(true)
		})

		it('returns true when document has files array', () => {
			wrapper.setData({
				document: { files: [{ id: 1 }] },
			})
			expect(wrapper.vm.isEnvelope).toBe(true)
		})

		it('returns false when files array is empty', () => {
			wrapper.setData({
				document: { files: [] },
			})
			expect(wrapper.vm.isEnvelope).toBe(false)
		})

		it('returns false for regular document', () => {
			wrapper.setData({
				document: { nodeType: 'file' },
			})
			expect(wrapper.vm.isEnvelope).toBe(false)
		})
	})

	describe('async signing rendering', () => {
		it('does not render Promise text in async signing mode', async () => {
			await wrapper.setData({
				isAsyncSigning: true,
			})

			expect(wrapper.html()).not.toContain('[object Promise]')
		})
	})

	describe('getValidityStatus method', () => {
		it('returns unknown when valid_to is missing', () => {
			const signer = {}
			expect(wrapper.vm.getValidityStatus(signer)).toBe('unknown')
		})

		it('returns expired when certificate has expired', () => {
			const pastDate = new Date()
			pastDate.setFullYear(pastDate.getFullYear() - 1)
			const signer = { valid_to: pastDate.toISOString() }
			expect(wrapper.vm.getValidityStatus(signer)).toBe('expired')
		})

		it('returns expiring when certificate expires within 30 days', () => {
			const soonDate = new Date()
			soonDate.setDate(soonDate.getDate() + 15) // 15 days from now
			const signer = { valid_to: soonDate.toISOString() }
			expect(wrapper.vm.getValidityStatus(signer)).toBe('expiring')
		})

		it('returns valid when certificate expires in more than 30 days', () => {
			const futureDate = new Date()
			futureDate.setDate(futureDate.getDate() + 60) // 60 days from now
			const signer = { valid_to: futureDate.toISOString() }
			expect(wrapper.vm.getValidityStatus(signer)).toBe('valid')
		})

		it('returns expired for certificate expiring today', () => {
			const today = new Date()
			today.setHours(0, 0, 0, 0)
			const signer = { valid_to: today.toISOString() }
			expect(wrapper.vm.getValidityStatus(signer)).toBe('expired')
		})
	})

	describe('getValidityStatusAtSigning method', () => {
		it('returns unknown when signed date is missing', () => {
			const signer = {
				valid_from: '2024-01-01T00:00:00Z',
				valid_to: '2025-01-01T00:00:00Z',
			}
			expect(wrapper.vm.getValidityStatusAtSigning(signer)).toBe('unknown')
		})

		it('returns unknown when valid_from is missing', () => {
			const signer = {
				signed: '2024-06-01T00:00:00Z',
				valid_to: '2025-01-01T00:00:00Z',
			}
			expect(wrapper.vm.getValidityStatusAtSigning(signer)).toBe('unknown')
		})

		it('returns unknown when valid_to is missing', () => {
			const signer = {
				signed: '2024-06-01T00:00:00Z',
				valid_from: '2024-01-01T00:00:00Z',
			}
			expect(wrapper.vm.getValidityStatusAtSigning(signer)).toBe('unknown')
		})

		it('returns valid when signed within validity period', () => {
			const signer = {
				signed: '2024-06-01T12:00:00Z',
				valid_from: '2024-01-01T00:00:00Z',
				valid_to: '2025-01-01T00:00:00Z',
			}
			expect(wrapper.vm.getValidityStatusAtSigning(signer)).toBe('valid')
		})

		it('returns expired when signed before valid_from', () => {
			const signer = {
				signed: '2023-12-31T23:59:59Z',
				valid_from: '2024-01-01T00:00:00Z',
				valid_to: '2025-01-01T00:00:00Z',
			}
			expect(wrapper.vm.getValidityStatusAtSigning(signer)).toBe('expired')
		})

		it('returns expired when signed after valid_to', () => {
			const signer = {
				signed: '2025-01-02T00:00:00Z',
				valid_from: '2024-01-01T00:00:00Z',
				valid_to: '2025-01-01T00:00:00Z',
			}
			expect(wrapper.vm.getValidityStatusAtSigning(signer)).toBe('expired')
		})

		it('returns valid when signed at exact valid_from time', () => {
			const timestamp = '2024-01-01T00:00:00Z'
			const signer = {
				signed: timestamp,
				valid_from: timestamp,
				valid_to: '2025-01-01T00:00:00Z',
			}
			expect(wrapper.vm.getValidityStatusAtSigning(signer)).toBe('valid')
		})

		it('returns valid when signed at exact valid_to time', () => {
			const timestamp = '2025-01-01T00:00:00Z'
			const signer = {
				signed: timestamp,
				valid_from: '2024-01-01T00:00:00Z',
				valid_to: timestamp,
			}
			expect(wrapper.vm.getValidityStatusAtSigning(signer)).toBe('valid')
		})
	})

	describe('hasValidationIssues method', () => {
		it('returns true when signature validation failed', () => {
			const signer = {
				signature_validation: { id: 2, label: 'Invalid' },
			}
			expect(wrapper.vm.hasValidationIssues(signer)).toBe(true)
		})

		it('returns true when certificate validation failed', () => {
			const signer = {
				signature_validation: { id: 1, label: 'Valid' },
				certificate_validation: { id: 2, label: 'Untrusted' },
			}
			expect(wrapper.vm.hasValidationIssues(signer)).toBe(true)
		})

		it('returns true when certificate is revoked', () => {
			const signer = {
				signature_validation: { id: 1, label: 'Valid' },
				certificate_validation: { id: 1, label: 'Trusted' },
				crl_validation: 'revoked',
			}
			expect(wrapper.vm.hasValidationIssues(signer)).toBe(true)
		})

		it('returns true when certificate was invalid at signing time', () => {
			const signer = {
				signature_validation: { id: 1, label: 'Valid' },
				certificate_validation: { id: 1, label: 'Trusted' },
				signed: '2023-12-31T23:59:59Z',
				valid_from: '2024-01-01T00:00:00Z',
				valid_to: '2025-01-01T00:00:00Z',
			}
			expect(wrapper.vm.hasValidationIssues(signer)).toBe(true)
		})

		it('returns true when certificate is currently expired', () => {
			const pastDate = new Date()
			pastDate.setFullYear(pastDate.getFullYear() - 1)
			const signer = {
				signature_validation: { id: 1, label: 'Valid' },
				certificate_validation: { id: 1, label: 'Trusted' },
				valid_to: pastDate.toISOString(),
			}
			expect(wrapper.vm.hasValidationIssues(signer)).toBe(true)
		})

		it('returns true when certificate is expiring soon', () => {
			const soonDate = new Date()
			soonDate.setDate(soonDate.getDate() + 15)
			const signer = {
				signature_validation: { id: 1, label: 'Valid' },
				certificate_validation: { id: 1, label: 'Trusted' },
				valid_to: soonDate.toISOString(),
			}
			expect(wrapper.vm.hasValidationIssues(signer)).toBe(true)
		})

		it('returns false when all validations pass', () => {
			const futureDate = new Date()
			futureDate.setDate(futureDate.getDate() + 60)
			const signer = {
				signature_validation: { id: 1, label: 'Valid' },
				certificate_validation: { id: 1, label: 'Trusted' },
				crl_validation: 'valid',
				valid_to: futureDate.toISOString(),
			}
			expect(wrapper.vm.hasValidationIssues(signer)).toBe(false)
		})
	})

	describe('camelCaseToTitleCase method', () => {
		it('converts camelCase to Title Case', () => {
			expect(wrapper.vm.camelCaseToTitleCase('camelCase')).toBe('Camel Case')
		})

		it('converts PascalCase to Title Case', () => {
			expect(wrapper.vm.camelCaseToTitleCase('PascalCase')).toBe('Pascal Case')
		})

		it('handles multiple capital letters', () => {
			expect(wrapper.vm.camelCaseToTitleCase('XMLHttpRequest')).toBe('XML Http Request')
		})

		it('capitalizes first letter of already spaced text', () => {
			expect(wrapper.vm.camelCaseToTitleCase('already spaced')).toBe('Already spaced')
		})

		it('handles single word', () => {
			expect(wrapper.vm.camelCaseToTitleCase('word')).toBe('Word')
		})

		it('handles empty string', () => {
			expect(wrapper.vm.camelCaseToTitleCase('')).toBe('')
		})
	})

	describe('getName method', () => {
		it('returns displayName when available', () => {
			const signer = { displayName: 'John Doe', email: 'john@example.com' }
			expect(wrapper.vm.getName(signer)).toBe('John Doe')
		})

		it('returns email when displayName is not available', () => {
			const signer = { email: 'john@example.com' }
			expect(wrapper.vm.getName(signer)).toBe('john@example.com')
		})

		it('returns signature validation label when neither displayName nor email available', () => {
			const signer = { signature_validation: { label: 'Certificate CN' } }
			expect(wrapper.vm.getName(signer)).toBe('Certificate CN')
		})

		it('returns Unknown when no identification available', () => {
			const signer = {}
			expect(wrapper.vm.getName(signer)).toBe('Unknown')
		})
	})

	describe('EXPIRATION_WARNING_DAYS constant', () => {
		it('is set to 30 days by default', () => {
			expect(wrapper.vm.EXPIRATION_WARNING_DAYS).toBe(30)
		})
	})

	// Vue Router 5 only preserves params that are part of the route path.
	// Routes like /f/validation/:uuid only have :uuid in the path.
	// State flags (isAfterSigned, isAsync) must travel via history.state,
	// not via route params — otherwise Vue Router 5 silently drops them.
	describe('isAfterSigned computed property - reads from history.state', () => {
		afterEach(() => {
			history.replaceState({}, '')
		})

		it('returns true when history.state.isAfterSigned is true', () => {
			history.pushState({ isAfterSigned: true }, '')
			expect(wrapper.vm.isAfterSigned).toBe(true)
		})

		it('returns false when history.state.isAfterSigned is false', () => {
			history.pushState({ isAfterSigned: false }, '')
			expect(wrapper.vm.isAfterSigned).toBe(false)
		})

		it('falls back to shouldFireAsyncConfetti when history.state has no isAfterSigned', async () => {
			history.pushState({}, '')
			await wrapper.setData({ shouldFireAsyncConfetti: true })
			expect(wrapper.vm.isAfterSigned).toBe(true)
		})

		it('returns false when history state has no isAfterSigned and shouldFireAsyncConfetti is false', () => {
			history.pushState({}, '')
			expect(wrapper.vm.isAfterSigned).toBe(false)
		})
	})

	// Vue Router 5 drops non-path params on navigation. isAsync must travel
	// via history.state so it survives the push from the signing page.
	describe('created() - async signing activation from history.state', () => {
		const UUID = '550e8400-e29b-41d4-a716-446655440000'
		let stateGetter: ReturnType<typeof vi.spyOn>

		beforeEach(() => {
			// Prevent the validate() floating Promise from crashing on
			// the undefined-return of the axios.get mock
			vi.mocked(axios.get).mockResolvedValue({ data: { ocs: { data: {} } } })
		})

		afterEach(() => {
			stateGetter?.mockRestore()
			vi.mocked(axios.get).mockReset()
		})

		// REGRESSION TEST: before the fix, Validation.vue checked $route.params.isAsync.
		// Vue Router 5 silently drops params not in the route path, so that check
		// was always false — confetti never fired.
		// After the fix, isAsync is read from history.state (passed via router's `state:`).
		// This test verifies the OLD trigger (route params) no longer activates async signing.
		it('does NOT set isAsyncSigning via $route.params (Vue Router 5 drops non-path params)', () => {
			stateGetter = vi.spyOn(window.history, 'state', 'get').mockReturnValue({} as any)
			const localWrapper = shallowMount(Validation, {
				mocks: {
					$route: { params: { uuid: UUID, isAsync: true }, query: {} },
					$router: { ...mockRouter, replace: vi.fn() },
				},
			})
			// $route.params.isAsync is true in the mock, BUT the fixed code no longer reads
			// from params — it reads from history.state (which is empty here).
			expect(localWrapper.vm.isAsyncSigning).toBe(false)
			expect(localWrapper.vm.shouldFireAsyncConfetti).toBe(false)
		})

		it('does not set isAsyncSigning when history.state has no isAsync flag', () => {
			stateGetter = vi.spyOn(window.history, 'state', 'get').mockReturnValue({} as any)
			const localWrapper = shallowMount(Validation, {
				mocks: {
					$route: { params: { uuid: UUID }, query: {} },
					$router: { ...mockRouter, replace: vi.fn() },
				},
			})
			expect(localWrapper.vm.isAsyncSigning).toBe(false)
			expect(localWrapper.vm.shouldFireAsyncConfetti).toBe(false)
		})
	})

	describe('handleValidationSuccess - confetti behavior', () => {
		// FILE_STATUS.SIGNED = 3
		const SIGNED_STATUS = 3

		it('fires confetti when document is signed and isAfterSigned returns true', () => {
			// Spy on the computed getter to simulate the route-param path
			// (Vue 3 mocked $route.params lacks reactivity in test env — covered separately)
			vi.spyOn(wrapper.vm, 'isAfterSigned', 'get').mockReturnValue(true)
			wrapper.vm.handleValidationSuccess({ status: SIGNED_STATUS, signers: [] })
			expect(mockAddConfetti).toHaveBeenCalledOnce()
		})

		it('fires confetti when document is signed and shouldFireAsyncConfetti is true', async () => {
			await wrapper.setData({ shouldFireAsyncConfetti: true })
			wrapper.vm.handleValidationSuccess({ status: SIGNED_STATUS, signers: [] })
			expect(mockAddConfetti).toHaveBeenCalledOnce()
		})

		it('fires confetti when all files in envelope are signed and shouldFireAsyncConfetti is true', async () => {
			await wrapper.setData({ shouldFireAsyncConfetti: true })
			wrapper.vm.handleValidationSuccess({
				status: 0,
				files: [
					{ status: SIGNED_STATUS },
					{ status: SIGNED_STATUS },
				],
				signers: [],
			})
			expect(mockAddConfetti).toHaveBeenCalledOnce()
		})

		it('fires confetti when current signer is signed and shouldFireAsyncConfetti is true', async () => {
			await wrapper.setData({ shouldFireAsyncConfetti: true })
			// SIGN_REQUEST_STATUS.SIGNED = 2
			wrapper.vm.handleValidationSuccess({
				status: 0,
				signers: [{ me: true, status: 2 }],
			})
			expect(mockAddConfetti).toHaveBeenCalledOnce()
		})

		it('does not fire confetti when document is not signed even if isAfterSigned is true', () => {
			const localWrapper = shallowMount(Validation, {
				mocks: {
					$route: { params: { isAfterSigned: true }, query: {} },
					$router: mockRouter,
				},
			})
			localWrapper.vm.handleValidationSuccess({ status: 1, signers: [] })
			expect(mockAddConfetti).not.toHaveBeenCalled()
		})

		it('does not fire confetti when document is signed but neither isAfterSigned nor shouldFireAsyncConfetti is true', () => {
			wrapper.vm.handleValidationSuccess({ status: SIGNED_STATUS, signers: [] })
			expect(mockAddConfetti).not.toHaveBeenCalled()
		})

		it('resets shouldFireAsyncConfetti to false after firing confetti', async () => {
			await wrapper.setData({ shouldFireAsyncConfetti: true })
			wrapper.vm.handleValidationSuccess({ status: SIGNED_STATUS, signers: [] })
			expect(wrapper.vm.shouldFireAsyncConfetti).toBe(false)
		})

		it('does not reset shouldFireAsyncConfetti when confetti is not fired', async () => {
			await wrapper.setData({ shouldFireAsyncConfetti: true })
			// document not signed → confetti won't fire
			wrapper.vm.handleValidationSuccess({ status: 1, signers: [] })
			expect(wrapper.vm.shouldFireAsyncConfetti).toBe(true)
		})

		it('does not fire confetti when isActiveView is false', async () => {
			await wrapper.setData({ shouldFireAsyncConfetti: true, isActiveView: false })
			wrapper.vm.handleValidationSuccess({ status: SIGNED_STATUS, signers: [] })
			expect(mockAddConfetti).not.toHaveBeenCalled()
		})
	})

	describe('handleSigningComplete method', () => {
		// FILE_STATUS.SIGNED = 3
		const SIGNED_STATUS = 3
		// SIGN_REQUEST_STATUS.SIGNED = 2
		const SIGNER_SIGNED_STATUS = 2

		it('sets isAsyncSigning to false when called', async () => {
			await wrapper.setData({ isAsyncSigning: true })
			vi.spyOn(wrapper.vm, 'refreshAfterAsyncSigning').mockResolvedValue(undefined)
			wrapper.vm.handleSigningComplete(null)
			expect(wrapper.vm.isAsyncSigning).toBe(false)
		})

		it('sets shouldFireAsyncConfetti to true when called', async () => {
			vi.spyOn(wrapper.vm, 'refreshAfterAsyncSigning').mockResolvedValue(undefined)
			wrapper.vm.handleSigningComplete(null)
			expect(wrapper.vm.shouldFireAsyncConfetti).toBe(true)
		})

		it('does nothing when isActiveView is false', async () => {
			await wrapper.setData({ isAsyncSigning: true, isActiveView: false })
			wrapper.vm.handleSigningComplete(null)
			expect(wrapper.vm.isAsyncSigning).toBe(true)
			expect(wrapper.vm.shouldFireAsyncConfetti).toBe(false)
		})

		describe('RULE: when a file is returned directly by SigningProgress', () => {
			it('fires confetti when the file has signed status', () => {
				const signedFile = { status: SIGNED_STATUS, signers: [] }
				wrapper.vm.handleSigningComplete(signedFile)
				expect(mockAddConfetti).toHaveBeenCalledOnce()
			})

			it('fires confetti when the current signer is marked as signed', () => {
				const fileWithSignedSigner = {
					status: 1,
					signers: [{ me: true, status: SIGNER_SIGNED_STATUS, signed: '2025-01-01T00:00:00Z' }],
				}
				wrapper.vm.handleSigningComplete(fileWithSignedSigner)
				expect(mockAddConfetti).toHaveBeenCalledOnce()
			})

			it('does not fire confetti when the file is not yet signed and no signer is marked as signed', () => {
				// This is a realistic scenario: SigningProgress emits 'completed'
				// with a file object whose status is still pending/partial
				const unsignedFile = { status: 1, signers: [{ me: true, status: 0 }] }
				wrapper.vm.handleSigningComplete(unsignedFile)
				expect(mockAddConfetti).not.toHaveBeenCalled()
			})
		})

		describe('RULE: when SigningProgress emits completed without a file (async polling path)', () => {
			it('fires confetti after polling returns a signed document', async () => {
				await wrapper.setData({ uuidToValidate: '550e8400-e29b-41d4-a716-446655440000' })

				// Simulate the validate call returning a signed document via handleValidationSuccess
				vi.spyOn(wrapper.vm, 'validate').mockImplementation(async () => {
					wrapper.vm.handleValidationSuccess({ status: SIGNED_STATUS, signers: [] })
				})

				wrapper.vm.handleSigningComplete(null)

				// Wait for the async polling loop to run
				await new Promise(resolve => setTimeout(resolve, 0))

				expect(mockAddConfetti).toHaveBeenCalledOnce()
			})

			it('fires confetti after polling finds that the current signer is signed', async () => {
				await wrapper.setData({ uuidToValidate: '550e8400-e29b-41d4-a716-446655440000' })

				vi.spyOn(wrapper.vm, 'validate').mockImplementation(async () => {
					wrapper.vm.handleValidationSuccess({
						status: 1,
						signers: [{ me: true, status: SIGNER_SIGNED_STATUS, signed: '2025-01-01T00:00:00Z' }],
					})
				})

				wrapper.vm.handleSigningComplete(null)
				await new Promise(resolve => setTimeout(resolve, 0))

				expect(mockAddConfetti).toHaveBeenCalledOnce()
			})
		})
	})
})
