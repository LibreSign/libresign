/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, describe, expect, it, beforeEach, vi } from 'vitest'
import { createL10nMock, interpolateL10n } from '../testHelpers/l10n.js'
import { shallowMount } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import JSConfetti from 'js-confetti'
import Validation from '../../views/Validation.vue'

type ValidationVm = {
	uuidToValidate: string
	hasInfo: boolean
	loading: boolean
	document: Record<string, any>
	legalInformation: string
	clickedValidate: boolean
	getUUID: boolean
	validationErrorMessage: string | null
	documentValidMessage: string | null
	isAsyncSigning: boolean
	shouldFireAsyncConfetti: boolean
	isActiveView: boolean
	EXPIRATION_WARNING_DAYS: number
	isAfterSigned: boolean
	isEnvelope: boolean
	validationComponent: unknown
	canValidate: boolean
	helperTextValidation: string
	getValidityStatus: (signer: Record<string, any>) => string
	getValidityStatusAtSigning: (signer: Record<string, any>) => string
	hasValidationIssues: (signer: Record<string, any>) => boolean
	camelCaseToTitleCase: (text: string) => string
	getName: (signer: Record<string, any>) => string
	handleValidationSuccess: (data: Record<string, any>) => void
	handleSigningComplete: (file: Record<string, any> | null) => void
	refreshAfterAsyncSigning: () => Promise<void>
	$nextTick: () => Promise<void>
}

type ValidationWrapper = ReturnType<typeof shallowMount> & {
	vm: ValidationVm
}

// Mock async components to prevent defineAsyncComponent from triggering
// pending Vite dev-server fetches that outlive the worker and cause
// "Closing rpc while fetch was pending" errors in Vitest.
vi.mock('../../components/validation/EnvelopeValidation.vue', () => ({ default: { template: '<div data-test="envelope-validation" />' } }))
vi.mock('../../components/validation/FileValidation.vue', () => ({ default: { template: '<div data-test="file-validation" />' } }))
vi.mock('../../components/validation/SigningProgress.vue', () => ({ default: { template: '<div />' } }))

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

vi.mock('@nextcloud/l10n', () => createL10nMock({
	t: (app: string, text: string, vars?: Record<string, string>) => {
		return interpolateL10n(text, vars)
	},
	n: (_app: string, singular: string, plural: string, count: number, vars?: Record<string, string>) => {
		const template = count === 1 ? singular : plural
		return interpolateL10n(template, { count, ...(vars ?? {}) })
	},
	translate: (app: string, text: string, vars?: Record<string, string>) => interpolateL10n(text, vars),
	translatePlural: (_app: string, singular: string, plural: string, count: number, vars?: Record<string, string>) => {
		const template = count === 1 ? singular : plural
		return interpolateL10n(template, { count, ...(vars ?? {}) })
	},
}))

// Mock router
const mockRoute = {
	params: {},
	query: {},
}

const mockRouter = {
	push: vi.fn(),
}

// Mock capabilities - show-confetti enabled by default so existing tests pass
vi.mock('@nextcloud/capabilities', () => ({
	getCapabilities: vi.fn(() => ({
		libresign: {
			config: {
				'show-confetti': true,
			},
		},
	})),
}))

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

vi.mock('../../store/files.js', () => {
	const filesStore = {
		files: {},
		addFile: vi.fn(),
		getFileIdByUuid: vi.fn(() => null),
		getFileIdByNodeId: vi.fn(() => null),
	}
	return {
		useFilesStore: vi.fn(() => filesStore),
	}
})

// Mock utils
vi.mock('../../utils/viewer.js', () => ({
	openDocument: vi.fn(),
}))

vi.mock('../../utils/fileStatus.js', () => ({
	getStatusLabel: vi.fn((status) => `Status: ${status}`),
}))

describe('Validation.vue - Business Logic', () => {
	let wrapper!: ValidationWrapper
	let mockAddConfetti: ReturnType<typeof vi.fn>
	const setVmState = async (patch: Record<string, unknown>) => {
		Object.entries(patch).forEach(([key, value]) => {
			;(wrapper.vm as unknown as Record<string, unknown>)[key] = value
		})
		await wrapper.vm.$nextTick()
	}

	beforeEach(async () => {
		vi.clearAllMocks()
		history.replaceState({}, '')
		mockAddConfetti = vi.fn()
		vi.mocked(axios.get).mockResolvedValue({ data: { ocs: { data: {} } } })
		const { useFilesStore } = await import('../../store/files.js')
		const filesStore = useFilesStore()
		filesStore.files = {}
		vi.mocked(filesStore.getFileIdByUuid).mockReturnValue(null)
		vi.mocked(filesStore.getFileIdByNodeId).mockReturnValue(null)
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
		}) as unknown as ValidationWrapper
	})

	afterEach(() => {
		history.replaceState({}, '')
		vi.mocked(axios.get).mockReset()
		wrapper.unmount()
	})

	describe('canValidate computed property', () => {
		it('returns false when uuidToValidate is empty', () => {
			wrapper.vm.uuidToValidate = ''
			expect(wrapper.vm.canValidate).toBe(false)
		})

		it('accepts numeric IDs', () => {
			wrapper.vm.uuidToValidate = '12345'
			expect(wrapper.vm.canValidate).toBe(true)
		})

		it('accepts valid UUID format', () => {
			wrapper.vm.uuidToValidate = '550e8400-e29b-41d4-a716-446655440000'
			expect(wrapper.vm.canValidate).toBe(true)
		})

		it('rejects invalid UUID format', () => {
			wrapper.vm.uuidToValidate = 'invalid-uuid-format'
			expect(wrapper.vm.canValidate).toBe(false)
		})

		it('rejects UUID with wrong version (not v4)', () => {
			wrapper.vm.uuidToValidate = '550e8400-e29b-31d4-a716-446655440000'
			expect(wrapper.vm.canValidate).toBe(false)
		})

		it('rejects UUID with wrong variant', () => {
			wrapper.vm.uuidToValidate = '550e8400-e29b-41d4-1716-446655440000'
			expect(wrapper.vm.canValidate).toBe(false)
		})
	})

	describe('helperTextValidation computed property', () => {
		it('shows error message for invalid UUID', () => {
			wrapper.vm.uuidToValidate = 'invalid'
			expect(wrapper.vm.helperTextValidation).toBe('Invalid UUID')
		})

		it('returns empty string for valid UUID', () => {
			wrapper.vm.uuidToValidate = '550e8400-e29b-41d4-a716-446655440000'
			expect(wrapper.vm.helperTextValidation).toBe('')
		})

		it('returns empty string when uuidToValidate is empty', () => {
			wrapper.vm.uuidToValidate = ''
			expect(wrapper.vm.helperTextValidation).toBe('')
		})
	})

	describe('isEnvelope computed property', () => {
		it('returns true when nodeType is envelope', () => {
			wrapper.vm.document = {
				document: { nodeType: 'envelope' },
			}.document
			expect(wrapper.vm.isEnvelope).toBe(true)
		})

		it('returns true when document has files array', () => {
			wrapper.vm.document = {
				document: { files: [{ id: 1 }] },
			}.document
			expect(wrapper.vm.isEnvelope).toBe(true)
		})

		it('returns false when files array is empty', () => {
			wrapper.vm.document = {
				document: { files: [] },
			}.document
			expect(wrapper.vm.isEnvelope).toBe(false)
		})

		it('returns false for regular document', () => {
			wrapper.vm.document = {
				document: { nodeType: 'file' },
			}.document
			expect(wrapper.vm.isEnvelope).toBe(false)
		})
	})

	describe('async signing rendering', () => {
		it('does not render Promise text in async signing mode', async () => {
			await setVmState({
				isAsyncSigning: true,
			})

			expect(wrapper.html()).not.toContain('[object Promise]')
		})

		it('uses component references instead of string names for validation content', async () => {
			wrapper.vm.document = { uuid: 'doc-uuid', nodeType: 'file', name: 'contract.pdf' }
			await wrapper.vm.$nextTick()

			expect(typeof wrapper.vm.validationComponent).toBe('object')
			expect(wrapper.vm.validationComponent).not.toBe('FileValidation')

			wrapper.vm.document = { uuid: 'doc-uuid', nodeType: 'envelope', name: 'envelope', files: [{ id: 1 }] }
			await wrapper.vm.$nextTick()

			expect(typeof wrapper.vm.validationComponent).toBe('object')
			expect(wrapper.vm.validationComponent).not.toBe('EnvelopeValidation')
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
			await setVmState({ shouldFireAsyncConfetti: true })
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
		let localWrapper: ValidationWrapper | null = null

		beforeEach(() => {
			// Prevent the validate() floating Promise from crashing on
			// the undefined-return of the axios.get mock
			vi.mocked(axios.get).mockResolvedValue({ data: { ocs: { data: {} } } })
		})

		afterEach(() => {
			localWrapper?.unmount()
			localWrapper = null
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
			localWrapper = shallowMount(Validation, {
				mocks: {
					$route: { params: { uuid: UUID, isAsync: true }, query: {} },
					$router: { ...mockRouter, replace: vi.fn() },
				},
			}) as unknown as ValidationWrapper
			// $route.params.isAsync is true in the mock, BUT the fixed code no longer reads
			// from params — it reads from history.state (which is empty here).
			expect(localWrapper.vm.isAsyncSigning).toBe(false)
			expect(localWrapper.vm.shouldFireAsyncConfetti).toBe(false)
		})

		it('does not set isAsyncSigning when history.state has no isAsync flag', () => {
			stateGetter = vi.spyOn(window.history, 'state', 'get').mockReturnValue({} as any)
			localWrapper = shallowMount(Validation, {
				mocks: {
					$route: { params: { uuid: UUID }, query: {} },
					$router: { ...mockRouter, replace: vi.fn() },
				},
			}) as unknown as ValidationWrapper
			expect(localWrapper.vm.isAsyncSigning).toBe(false)
			expect(localWrapper.vm.shouldFireAsyncConfetti).toBe(false)
		})
	})

	describe('handleValidationSuccess - confetti behavior', () => {
		// FILE_STATUS.SIGNED = 3
		const SIGNED_STATUS = 3
		const createLoadedValidationDocument = (patch: Record<string, unknown> = {}) => ({
			uuid: '550e8400-e29b-41d4-a716-446655440000',
			name: 'contract.pdf',
			nodeId: 100,
			nodeType: 'file',
			status: 1,
			signers: [],
			...patch,
		})

		it('fires confetti when document is signed and isAfterSigned returns true', () => {
			history.pushState({ isAfterSigned: true }, '')
			wrapper.vm.handleValidationSuccess(createLoadedValidationDocument({ status: SIGNED_STATUS }))
			expect(mockAddConfetti).toHaveBeenCalledOnce()
		})

		it('fires confetti when document is signed and shouldFireAsyncConfetti is true', async () => {
			await setVmState({ shouldFireAsyncConfetti: true })
			wrapper.vm.handleValidationSuccess(createLoadedValidationDocument({ status: SIGNED_STATUS }))
			expect(mockAddConfetti).toHaveBeenCalledOnce()
		})

		it('fires confetti when all files in envelope are signed and shouldFireAsyncConfetti is true', async () => {
			await setVmState({ shouldFireAsyncConfetti: true })
			wrapper.vm.handleValidationSuccess(createLoadedValidationDocument({
				nodeType: 'envelope',
				status: 0,
				files: [
					{ status: SIGNED_STATUS },
					{ status: SIGNED_STATUS },
				],
			}))
			expect(mockAddConfetti).toHaveBeenCalledOnce()
		})

		it('fires confetti when current signer is signed and shouldFireAsyncConfetti is true', async () => {
			await setVmState({ shouldFireAsyncConfetti: true })
			// SIGN_REQUEST_STATUS.SIGNED = 2
			wrapper.vm.handleValidationSuccess(createLoadedValidationDocument({
				status: 0,
				signers: [{ me: true, status: 2 }],
			}))
			expect(mockAddConfetti).toHaveBeenCalledOnce()
		})

		it('does not fire confetti when document is not signed even if isAfterSigned is true', () => {
			const lw = shallowMount(Validation, {
				mocks: {
					$route: { params: { isAfterSigned: true }, query: {} },
					$router: mockRouter,
				},
			}) as unknown as ValidationWrapper
			lw.vm.handleValidationSuccess(createLoadedValidationDocument({ status: 1 }))
			expect(mockAddConfetti).not.toHaveBeenCalled()
			lw.unmount()
		})

		it('does not fire confetti when document is signed but neither isAfterSigned nor shouldFireAsyncConfetti is true', () => {
			wrapper.vm.handleValidationSuccess(createLoadedValidationDocument({ status: SIGNED_STATUS }))
			expect(mockAddConfetti).not.toHaveBeenCalled()
		})

		it('resets shouldFireAsyncConfetti to false after firing confetti', async () => {
			await setVmState({ shouldFireAsyncConfetti: true })
			wrapper.vm.handleValidationSuccess(createLoadedValidationDocument({ status: SIGNED_STATUS }))
			expect(wrapper.vm.shouldFireAsyncConfetti).toBe(false)
		})

		it('does not reset shouldFireAsyncConfetti when confetti is not fired', async () => {
			await setVmState({ shouldFireAsyncConfetti: true })
			// document not signed → confetti won't fire
			wrapper.vm.handleValidationSuccess(createLoadedValidationDocument({ status: 1 }))
			expect(wrapper.vm.shouldFireAsyncConfetti).toBe(true)
		})

		it('does not fire confetti when isActiveView is false', async () => {
			await setVmState({ shouldFireAsyncConfetti: true, isActiveView: false })
			wrapper.vm.handleValidationSuccess(createLoadedValidationDocument({ status: SIGNED_STATUS }))
			expect(mockAddConfetti).not.toHaveBeenCalled()
		})

		it('does not fire confetti when show-confetti capability is disabled', async () => {
			vi.mocked(getCapabilities).mockReturnValueOnce({
				libresign: {
					config: {
						'show-confetti': false,
					},
				},
			} as ReturnType<typeof getCapabilities>)
			history.pushState({ isAfterSigned: true }, '')
			wrapper.vm.handleValidationSuccess(createLoadedValidationDocument({ status: SIGNED_STATUS }))
			expect(mockAddConfetti).not.toHaveBeenCalled()
		})

		it('syncs a tracked signed document into files store', async () => {
			const { useFilesStore } = await import('../../store/files.js')
			const filesStore = useFilesStore()
			vi.mocked(filesStore.getFileIdByUuid).mockReturnValue(100)

			wrapper.vm.handleValidationSuccess(createLoadedValidationDocument({ status: SIGNED_STATUS }))

			expect(filesStore.addFile).toHaveBeenCalledWith(expect.objectContaining({
				id: 100,
				uuid: '550e8400-e29b-41d4-a716-446655440000',
				status: SIGNED_STATUS,
			}), { detailsLoaded: true })
		})

		it('syncs tracked envelope children when all files are signed', async () => {
			const { useFilesStore } = await import('../../store/files.js')
			const filesStore = useFilesStore()
			vi.mocked(filesStore.getFileIdByUuid).mockImplementation((uuid: string) => {
				if (uuid === '550e8400-e29b-41d4-a716-446655440000') {
					return 100
				}
				return null
			})
			vi.mocked(filesStore.getFileIdByNodeId).mockImplementation((nodeId: number) => {
				if (nodeId === 201) {
					return 201
				}
				if (nodeId === 202) {
					return 202
				}
				return null
			})

			wrapper.vm.handleValidationSuccess(createLoadedValidationDocument({
				nodeType: 'envelope',
				status: 0,
				files: [
					{ id: 201, uuid: 'child-1', nodeId: 201, name: 'child-1.pdf', status: SIGNED_STATUS },
					{ id: 202, uuid: 'child-2', nodeId: 202, name: 'child-2.pdf', status: SIGNED_STATUS },
				],
			}))

			expect(filesStore.addFile).toHaveBeenCalledTimes(3)
			expect(filesStore.addFile).toHaveBeenNthCalledWith(1, expect.objectContaining({ id: 100 }), { detailsLoaded: true })
			expect(filesStore.addFile).toHaveBeenNthCalledWith(2, expect.objectContaining({ id: 201, status: SIGNED_STATUS }), { detailsLoaded: true })
			expect(filesStore.addFile).toHaveBeenNthCalledWith(3, expect.objectContaining({ id: 202, status: SIGNED_STATUS }), { detailsLoaded: true })
		})

		it('does not sync untracked documents into files store', async () => {
			const { useFilesStore } = await import('../../store/files.js')
			const filesStore = useFilesStore()

			wrapper.vm.handleValidationSuccess(createLoadedValidationDocument({ status: SIGNED_STATUS }))

			expect(filesStore.addFile).not.toHaveBeenCalled()
		})
	})

	describe('handleSigningComplete method', () => {
		// FILE_STATUS.SIGNED = 3
		const SIGNED_STATUS = 3
		// SIGN_REQUEST_STATUS.SIGNED = 2
		const SIGNER_SIGNED_STATUS = 2
		const createLoadedValidationDocument = (patch: Record<string, unknown> = {}) => ({
			uuid: '550e8400-e29b-41d4-a716-446655440000',
			name: 'contract.pdf',
			nodeId: 100,
			nodeType: 'file',
			status: 1,
			signers: [],
			...patch,
		})

		it('sets isAsyncSigning to false when called', async () => {
			await setVmState({ isAsyncSigning: true })
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
			await setVmState({ isAsyncSigning: true, isActiveView: false })
			wrapper.vm.handleSigningComplete(null)
			expect(wrapper.vm.isAsyncSigning).toBe(true)
			expect(wrapper.vm.shouldFireAsyncConfetti).toBe(false)
		})

		describe('RULE: when a file is returned directly by SigningProgress', () => {
			it('fires confetti when the file has signed status', () => {
				history.pushState({ isAfterSigned: true }, '')
				const signedFile = createLoadedValidationDocument({ status: SIGNED_STATUS })
				wrapper.vm.handleSigningComplete(signedFile)
				expect(mockAddConfetti).toHaveBeenCalledOnce()
			})

			it('fires confetti when the current signer is marked as signed', () => {
				const fileWithSignedSigner = createLoadedValidationDocument({
					status: 1,
					signers: [{ me: true, status: SIGNER_SIGNED_STATUS, signed: '2025-01-01T00:00:00Z' }],
				})
				wrapper.vm.handleSigningComplete(fileWithSignedSigner)
				expect(mockAddConfetti).toHaveBeenCalledOnce()
			})

			it('does not fire confetti when the file is not yet signed and no signer is marked as signed', () => {
				// This is a realistic scenario: SigningProgress emits 'completed'
				// with a file object whose status is still pending/partial
				const unsignedFile = createLoadedValidationDocument({ status: 1, signers: [{ me: true, status: 0 }] })
				wrapper.vm.handleSigningComplete(unsignedFile)
				expect(mockAddConfetti).not.toHaveBeenCalled()
			})
		})

		describe('RULE: when SigningProgress emits completed without a file (async polling path)', () => {
			it('fires confetti after polling returns a signed document', async () => {
				await setVmState({ uuidToValidate: '550e8400-e29b-41d4-a716-446655440000' })
				history.pushState({ isAfterSigned: true }, '')
				vi.mocked(axios.get).mockResolvedValueOnce({ data: { ocs: { data: createLoadedValidationDocument({ status: SIGNED_STATUS }) } } })

				wrapper.vm.handleSigningComplete(null)

				// Wait for the async polling loop to run
				await new Promise(resolve => setTimeout(resolve, 0))

				expect(mockAddConfetti).toHaveBeenCalledOnce()
			})

			it('fires confetti after polling finds that the current signer is signed', async () => {
				await setVmState({ uuidToValidate: '550e8400-e29b-41d4-a716-446655440000' })
				history.pushState({ isAfterSigned: true }, '')
				vi.mocked(axios.get).mockResolvedValueOnce({
					data: {
						ocs: {
							data: createLoadedValidationDocument({
								status: 1,
								signers: [{ me: true, status: SIGNER_SIGNED_STATUS, signed: '2025-01-01T00:00:00Z' }],
							}),
						},
					},
				})

				wrapper.vm.handleSigningComplete(null)
				await new Promise(resolve => setTimeout(resolve, 0))

				expect(mockAddConfetti).toHaveBeenCalledOnce()
			})
		})
	})
})
