/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import Validation from '../../views/Validation.vue'

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
	let wrapper: any

	beforeEach(() => {
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
})
