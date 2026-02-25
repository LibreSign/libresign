/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import SignerDetails from '../../../components/validation/SignerDetails.vue'

describe('SignerDetails.vue - Business Logic', () => {
	type SignerDetailsProps = {
		signer?: Record<string, unknown>
		[key: string]: unknown
	}

	const createWrapper = (propsData: SignerDetailsProps = {}) => {
		return shallowMount(SignerDetails, {
			props: {
				signer: {
					signed: '2024-06-01T12:00:00Z',
					displayName: 'Test Signer',
					...propsData.signer,
				},
				...propsData,
			},
			stubs: {
				NcAvatar: true,
				NcButton: true,
				NcIconSvgWrapper: true,
				NcListItem: true,
				NcNoteCard: true,
			},
		})
	}

	let wrapper: ReturnType<typeof createWrapper>

	beforeEach(() => {
		wrapper = createWrapper()
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

		it('returns name when displayName and email are not available', () => {
			const signer = { name: 'Fallback Name' }
			expect(wrapper.vm.getName(signer)).toBe('Fallback Name')
		})

		it('returns Unknown when no identification available', () => {
			const signer = {}
			expect(wrapper.vm.getName(signer)).toBe('Unknown')
		})

		it('prefers displayName over email and name', () => {
			const signer = {
				displayName: 'Display',
				email: 'email@test.com',
				name: 'Name',
			}
			expect(wrapper.vm.getName(signer)).toBe('Display')
		})
	})

	describe('hasValidationIssues method', () => {
		it('returns false when all validations pass', () => {
			const signer = {
				signature_validation: { id: 1 },
				certificate_validation: { id: 1 },
				crl_validation: 'valid',
			}
			expect(wrapper.vm.hasValidationIssues(signer)).toBe(false)
		})

		it('returns true when signature validation failed', () => {
			const signer = {
				signature_validation: { id: 2 },
				certificate_validation: { id: 1 },
			}
			expect(wrapper.vm.hasValidationIssues(signer)).toBe(true)
		})

		it('returns true when certificate validation failed', () => {
			const signer = {
				signature_validation: { id: 1 },
				certificate_validation: { id: 2 },
			}
			expect(wrapper.vm.hasValidationIssues(signer)).toBe(true)
		})

		it('returns true when certificate was revoked before signing', () => {
			const signer = {
				signature_validation: { id: 1 },
				certificate_validation: { id: 1 },
				crl_validation: 'revoked',
				crl_revoked_at: '2024-05-01T00:00:00Z',
				signed: '2024-06-01T00:00:00Z',
			}
			expect(wrapper.vm.hasValidationIssues(signer)).toBe(true)
		})

		it('returns false when certificate was revoked after signing', () => {
			const signer = {
				signature_validation: { id: 1 },
				certificate_validation: { id: 1 },
				crl_validation: 'revoked',
				crl_revoked_at: '2024-07-01T00:00:00Z',
				signed: '2024-06-01T00:00:00Z',
			}
			expect(wrapper.vm.hasValidationIssues(signer)).toBe(false)
		})
	})

	describe('isRevokedBeforeSigning method', () => {
		it('returns false when crl_validation is not revoked', () => {
			const signer = {
				crl_validation: 'valid',
				crl_revoked_at: '2024-05-01T00:00:00Z',
				signed: '2024-06-01T00:00:00Z',
			}
			expect(wrapper.vm.isRevokedBeforeSigning(signer)).toBe(false)
		})

		it('returns true when revoked_at is missing', () => {
			const signer = {
				crl_validation: 'revoked',
				signed: '2024-06-01T00:00:00Z',
			}
			expect(wrapper.vm.isRevokedBeforeSigning(signer)).toBe(true)
		})

		it('returns true when signed date is missing', () => {
			const signer = {
				crl_validation: 'revoked',
				crl_revoked_at: '2024-05-01T00:00:00Z',
			}
			expect(wrapper.vm.isRevokedBeforeSigning(signer)).toBe(true)
		})

		it('returns true when revoked before signing', () => {
			const signer = {
				crl_validation: 'revoked',
				crl_revoked_at: '2024-05-01T00:00:00Z',
				signed: '2024-06-01T00:00:00Z',
			}
			expect(wrapper.vm.isRevokedBeforeSigning(signer)).toBe(true)
		})

		it('returns false when revoked after signing', () => {
			const signer = {
				crl_validation: 'revoked',
				crl_revoked_at: '2024-07-01T00:00:00Z',
				signed: '2024-06-01T00:00:00Z',
			}
			expect(wrapper.vm.isRevokedBeforeSigning(signer)).toBe(false)
		})

		it('returns true when revoked exactly at signing time', () => {
			const timestamp = '2024-06-01T12:00:00Z'
			const signer = {
				crl_validation: 'revoked',
				crl_revoked_at: timestamp,
				signed: timestamp,
			}
			expect(wrapper.vm.isRevokedBeforeSigning(signer)).toBe(true)
		})

		it('returns true when revoked_at date is invalid', () => {
			const signer = {
				crl_validation: 'revoked',
				crl_revoked_at: 'invalid-date',
				signed: '2024-06-01T00:00:00Z',
			}
			expect(wrapper.vm.isRevokedBeforeSigning(signer)).toBe(true)
		})

		it('returns true when signed date is invalid', () => {
			const signer = {
				crl_validation: 'revoked',
				crl_revoked_at: '2024-05-01T00:00:00Z',
				signed: 'invalid-date',
			}
			expect(wrapper.vm.isRevokedBeforeSigning(signer)).toBe(true)
		})
	})

	describe('getValidityStatus method', () => {
		it('returns valid when valid_to is missing', () => {
			const signer = {}
			expect(wrapper.vm.getValidityStatus(signer)).toBe('valid')
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

		it('returns expiring when certificate expires in exactly 29 days', () => {
			const date = new Date()
			date.setDate(date.getDate() + 29)
			const signer = { valid_to: date.toISOString() }
			expect(wrapper.vm.getValidityStatus(signer)).toBe('expiring')
		})

		it('returns valid when certificate expires in exactly 31 days', () => {
			const date = new Date()
			date.setDate(date.getDate() + 31)
			const signer = { valid_to: date.toISOString() }
			expect(wrapper.vm.getValidityStatus(signer)).toBe('valid')
		})
	})

	describe('getValidityStatusAtSigning method', () => {
		it('returns unknown when valid_from is missing', () => {
			const signer = {
				valid_to: '2025-01-01T00:00:00Z',
				signed: '2024-06-01T00:00:00Z',
			}
			expect(wrapper.vm.getValidityStatusAtSigning(signer)).toBe('unknown')
		})

		it('returns unknown when valid_to is missing', () => {
			const signer = {
				valid_from: '2024-01-01T00:00:00Z',
				signed: '2024-06-01T00:00:00Z',
			}
			expect(wrapper.vm.getValidityStatusAtSigning(signer)).toBe('unknown')
		})

		it('returns unknown when signed is missing', () => {
			const signer = {
				valid_from: '2024-01-01T00:00:00Z',
				valid_to: '2025-01-01T00:00:00Z',
			}
			expect(wrapper.vm.getValidityStatusAtSigning(signer)).toBe('unknown')
		})

		it('returns valid when signed within validity period', () => {
			const signer = {
				valid_from: '2024-01-01T00:00:00Z',
				valid_to: '2025-01-01T00:00:00Z',
				signed: '2024-06-01T12:00:00Z',
			}
			expect(wrapper.vm.getValidityStatusAtSigning(signer)).toBe('valid')
		})

		it('returns invalid when signed before valid_from', () => {
			const signer = {
				valid_from: '2024-01-01T00:00:00Z',
				valid_to: '2025-01-01T00:00:00Z',
				signed: '2023-12-31T23:59:59Z',
			}
			expect(wrapper.vm.getValidityStatusAtSigning(signer)).toBe('invalid')
		})

		it('returns invalid when signed after valid_to', () => {
			const signer = {
				valid_from: '2024-01-01T00:00:00Z',
				valid_to: '2025-01-01T00:00:00Z',
				signed: '2025-01-02T00:00:00Z',
			}
			expect(wrapper.vm.getValidityStatusAtSigning(signer)).toBe('invalid')
		})

		it('returns valid when signed exactly at valid_from', () => {
			const timestamp = '2024-01-01T00:00:00Z'
			const signer = {
				valid_from: timestamp,
				valid_to: '2025-01-01T00:00:00Z',
				signed: timestamp,
			}
			expect(wrapper.vm.getValidityStatusAtSigning(signer)).toBe('valid')
		})

		it('returns valid when signed exactly at valid_to', () => {
			const timestamp = '2025-01-01T00:00:00Z'
			const signer = {
				valid_from: '2024-01-01T00:00:00Z',
				valid_to: timestamp,
				signed: timestamp,
			}
			expect(wrapper.vm.getValidityStatusAtSigning(signer)).toBe('valid')
		})
	})

	describe('getCrlValidationIconClass method', () => {
		it('returns icon-error when revoked before signing', () => {
			const signer = {
				crl_validation: 'revoked',
				crl_revoked_at: '2024-05-01T00:00:00Z',
				signed: '2024-06-01T00:00:00Z',
			}
			expect(wrapper.vm.getCrlValidationIconClass(signer)).toBe('icon-error')
		})

		it('returns icon-success when revoked after signing', () => {
			const signer = {
				crl_validation: 'revoked',
				crl_revoked_at: '2024-07-01T00:00:00Z',
				signed: '2024-06-01T00:00:00Z',
			}
			expect(wrapper.vm.getCrlValidationIconClass(signer)).toBe('icon-success')
		})

		it('returns icon-success for valid CRL', () => {
			const signer = { crl_validation: 'valid' }
			expect(wrapper.vm.getCrlValidationIconClass(signer)).toBe('icon-success')
		})

		it('returns icon-warning for missing CRL', () => {
			const signer = { crl_validation: 'missing' }
			expect(wrapper.vm.getCrlValidationIconClass(signer)).toBe('icon-warning')
		})

		it('returns icon-warning for unknown status', () => {
			const signer = { crl_validation: 'unknown_status' }
			expect(wrapper.vm.getCrlValidationIconClass(signer)).toBe('icon-warning')
		})
	})

	describe('hasValidationStatus method', () => {
		it('returns true when signature_validation exists', () => {
			const signer = { signature_validation: { id: 1 } }
			expect(wrapper.vm.hasValidationStatus(signer)).toBe(true)
		})

		it('returns true when certificate_validation exists', () => {
			const signer = { certificate_validation: { id: 1 } }
			expect(wrapper.vm.hasValidationStatus(signer)).toBe(true)
		})

		it('returns true when crl_validation exists', () => {
			const signer = { crl_validation: 'valid' }
			expect(wrapper.vm.hasValidationStatus(signer)).toBe(true)
		})

		it('returns true when all date fields exist', () => {
			const signer = {
				valid_from: '2024-01-01T00:00:00Z',
				valid_to: '2025-01-01T00:00:00Z',
				signed: '2024-06-01T00:00:00Z',
			}
			expect(wrapper.vm.hasValidationStatus(signer)).toBe(true)
		})

		it('returns false when no validation info exists', () => {
			const signer = {}
			expect(wrapper.vm.hasValidationStatus(signer)).toBe(false)
		})

		it('returns false when date fields are incomplete', () => {
			const signer = {
				valid_from: '2024-01-01T00:00:00Z',
				valid_to: '2025-01-01T00:00:00Z',
			}
			expect(wrapper.vm.hasValidationStatus(signer)).toBe(false)
		})
	})

	describe('toggleOpen method', () => {
		it('toggles isOpen when signer has signed', () => {
			wrapper = createWrapper({ signer: { signed: '2024-06-01T00:00:00Z' } })
			expect(wrapper.vm.isOpen).toBe(false)

			wrapper.vm.toggleOpen()
			expect(wrapper.vm.isOpen).toBe(true)

			wrapper.vm.toggleOpen()
			expect(wrapper.vm.isOpen).toBe(false)
		})

		it('does not toggle when signer has not signed', () => {
			wrapper = createWrapper({ signer: { signed: null } })
			expect(wrapper.vm.isOpen).toBe(false)

			wrapper.vm.toggleOpen()
			expect(wrapper.vm.isOpen).toBe(false)
		})

		it('does not toggle when signed is undefined', () => {
			wrapper = createWrapper({ signer: {} })
			expect(wrapper.vm.isOpen).toBe(false)

			wrapper.vm.toggleOpen()
			expect(wrapper.vm.isOpen).toBe(false)
		})
	})

	describe('getSignatureValidationMessage method', () => {
		it('returns success message when validation passed', () => {
			const signer = { signature_validation: { id: 1 } }
			expect(wrapper.vm.getSignatureValidationMessage(signer)).toBe('Document integrity verified')
		})

		it('returns validation message when validation failed', () => {
			const signer = {
				signature_validation: { id: 2, message: 'Signature invalid' },
			}
			expect(wrapper.vm.getSignatureValidationMessage(signer)).toBe('Signature invalid')
		})

		it('returns default message when no message provided', () => {
			const signer = { signature_validation: { id: 2 } }
			expect(wrapper.vm.getSignatureValidationMessage(signer)).toBe('Document integrity check failed')
		})
	})

	describe('getCertificateTrustMessage method', () => {
		it('returns trusted message with trustedBy info', () => {
			const signer = {
				certificate_validation: { id: 1, trustedBy: 'Custom CA' },
			}
			expect(wrapper.vm.getCertificateTrustMessage(signer)).toContain('Trusted')
			expect(wrapper.vm.getCertificateTrustMessage(signer)).toContain('Custom CA')
		})

		it('returns trusted message with default LibreSign CA', () => {
			const signer = {
				certificate_validation: { id: 1 },
			}
			expect(wrapper.vm.getCertificateTrustMessage(signer)).toContain('Trusted')
			expect(wrapper.vm.getCertificateTrustMessage(signer)).toContain('LibreSign CA')
		})

		it('returns validation message when trust failed', () => {
			const signer = {
				certificate_validation: { id: 2, message: 'Certificate not trusted' },
			}
			expect(wrapper.vm.getCertificateTrustMessage(signer)).toBe('Certificate not trusted')
		})

		it('returns default untrusted message when no message provided', () => {
			const signer = {
				certificate_validation: { id: 2 },
			}
			expect(wrapper.vm.getCertificateTrustMessage(signer)).toBe('Trust Chain: Not Trusted')
		})
	})

	describe('integration scenarios', () => {
		it('correctly identifies a fully valid signature', () => {
			const validSigner = {
				displayName: 'Valid Signer',
				signed: '2024-06-01T00:00:00Z',
				signature_validation: { id: 1 },
				certificate_validation: { id: 1 },
				crl_validation: 'valid',
				valid_from: '2024-01-01T00:00:00Z',
				valid_to: '2025-01-01T00:00:00Z',
			}

			wrapper = createWrapper({ signer: validSigner })
			expect(wrapper.vm.hasValidationIssues(validSigner)).toBe(false)
			expect(wrapper.vm.getValidityStatusAtSigning(validSigner)).toBe('valid')
			expect(wrapper.vm.hasValidationStatus(validSigner)).toBe(true)
		})

		it('correctly identifies signature with revoked certificate after signing', () => {
			const signer = {
				displayName: 'Revoked After Signing',
				signed: '2024-06-01T00:00:00Z',
				signature_validation: { id: 1 },
				certificate_validation: { id: 1 },
				crl_validation: 'revoked',
				crl_revoked_at: '2024-07-01T00:00:00Z',
			}

			wrapper = createWrapper({ signer })
			expect(wrapper.vm.isRevokedBeforeSigning(signer)).toBe(false)
			expect(wrapper.vm.hasValidationIssues(signer)).toBe(false)
			expect(wrapper.vm.getCrlValidationIconClass(signer)).toBe('icon-success')
		})

		it('correctly identifies signature with revoked certificate before signing', () => {
			const signer = {
				displayName: 'Revoked Before Signing',
				signed: '2024-06-01T00:00:00Z',
				signature_validation: { id: 1 },
				certificate_validation: { id: 1 },
				crl_validation: 'revoked',
				crl_revoked_at: '2024-05-01T00:00:00Z',
			}

			wrapper = createWrapper({ signer })
			expect(wrapper.vm.isRevokedBeforeSigning(signer)).toBe(true)
			expect(wrapper.vm.hasValidationIssues(signer)).toBe(true)
			expect(wrapper.vm.getCrlValidationIconClass(signer)).toBe('icon-error')
		})
	})
})
