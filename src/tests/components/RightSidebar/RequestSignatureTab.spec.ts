/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
let RequestSignatureTab: any
import { FILE_STATUS } from '../../../constants.js'

// Mock translation function
;(global as any).t = vi.fn((app: any, msg: any) => msg)

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => {
		if (key === 'config') {
			return {
				'sign-elements': { 'is-available': true },
				'identification_documents': { enabled: false },
			}
		}
		if (key === 'can_request_sign') return true
		return defaultValue
	}),
}))

vi.mock('@nextcloud/capabilities', () => ({
	getCapabilities: vi.fn(() => ({
		libresign: {
			config: {
				'sign-elements': { 'is-available': true },
			},
		},
	})),
}))

vi.mock('@nextcloud/event-bus', () => ({
	subscribe: vi.fn(),
	unsubscribe: vi.fn(),
	emit: vi.fn(),
}))

vi.mock('@nextcloud/dialogs')
vi.mock('@nextcloud/axios')
vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path) => `/ocs${path}`),
	generateUrl: vi.fn((path) => path),
	getRootUrl: vi.fn(() => ''),
}))

vi.mock('@libresign/pdf-elements', () => ({
	setWorkerPath: vi.fn(),
}))

describe('RequestSignatureTab - Critical Business Rules', () => {
	let wrapper: any
	let filesStore: any
	const updateFile = async (patch: any) => {
		const current = filesStore.files[1] || { id: 1 }
		await filesStore.addFile({ ...current, ...patch, id: 1 })
		await wrapper.vm.$nextTick()
	}
	const updateMethods = async (methods: any) => {
		await wrapper.setData({ methods })
	}

	beforeEach(async () => {
		setActivePinia(createPinia())
		RequestSignatureTab = (await import('../../../components/RightSidebar/RequestSignatureTab.vue')).default
		const { useFilesStore } = await import('../../../store/files.js')
		filesStore = useFilesStore()

		await filesStore.addFile({
			id: 1,
			name: 'test.pdf',
			status: FILE_STATUS.DRAFT,
			signers: [],
			nodeType: 'file',
		})
		filesStore.selectFile(1)
		filesStore.canRequestSign = true

		wrapper = shallowMount(RequestSignatureTab, {
			mocks: {
				t: (app: any, text: any) => text,
			},
			stubs: {
				NcButton: true,
				NcCheckboxRadioSwitch: true,
				NcNoteCard: true,
				NcActionInput: true,
				NcActionButton: true,
				NcFormBox: true,
				NcLoadingIcon: true,
				Signers: true,
				SigningProgress: true,
				AccountPlus: true,
				ChartGantt: true,
				FileMultiple: true,
				Send: true,
				Delete: true,
				Bell: true,
				Draw: true,
				Pencil: true,
				MessageText: true,
				OrderNumericAscending: true,
			},
		})
	})

	describe('RULE: showDocMdpWarning when DocMDP level prevents changes', () => {
		it('shows warning when DocMDP level is 1 with existing signers', async () => {
			await updateFile({
				docmdpLevel: 1,
				signers: [{ email: 'test@example.com', signed: [] }],
			})

			expect(wrapper.vm.showDocMdpWarning).toBe(true)
		})

		it('hides warning when DocMDP level is not 1', async () => {
			await updateFile({
				docmdpLevel: 2,
				signers: [{ email: 'test@example.com', signed: [] }],
			})

			expect(wrapper.vm.showDocMdpWarning).toBe(false)
		})

		it('hides warning when no signers exist', async () => {
			await updateFile({ docmdpLevel: 1, signers: [] })

			expect(wrapper.vm.showDocMdpWarning).toBe(false)
		})
	})

	describe('RULE: isOriginalFileDeleted detection', () => {
		it('detects when original file is deleted', async () => {
			await updateFile({ metadata: { original_file_deleted: true } })

			expect(wrapper.vm.isOriginalFileDeleted).toBe(true)
		})

		it('returns false when file is not deleted', async () => {
			await updateFile({ metadata: { original_file_deleted: false } })

			expect(wrapper.vm.isOriginalFileDeleted).toBe(false)
		})

		it('returns false when metadata is missing', async () => {
			await updateFile({ metadata: undefined })

			expect(wrapper.vm.isOriginalFileDeleted).toBe(false)
		})
	})

	describe('RULE: isEnvelope detection', () => {
		it('detects envelope by nodeType', async () => {
			await updateFile({ nodeType: 'envelope' })

			expect(wrapper.vm.isEnvelope).toBe(true)
		})

		it('returns false for regular file', async () => {
			await updateFile({ nodeType: 'file' })

			expect(wrapper.vm.isEnvelope).toBe(false)
		})
	})

	describe('RULE: envelopeFilesCount calculation', () => {
		it('returns filesCount from document', async () => {
			await updateFile({ filesCount: 5 })

			expect(wrapper.vm.envelopeFilesCount).toBe(5)
		})

		it('defaults to 0 when neither available', () => {
			expect(wrapper.vm.envelopeFilesCount).toBe(0)
		})
	})

	describe('RULE: hasSigners detection', () => {
		it('returns true when document has signers', async () => {
			await updateFile({ signers: [{ email: 'test@example.com', signed: [] }] })

			expect(wrapper.vm.hasSigners).toBe(true)
		})

		it('returns false when signers array is empty', async () => {
			await updateFile({ signers: [] })

			expect(wrapper.vm.hasSigners).toBe(false)
		})

		it('returns false when signers is null', async () => {
			await updateFile({ signers: null })

			expect(wrapper.vm.hasSigners).toBe(false)
		})
	})

	describe('RULE: showPreserveOrder when multiple signers', () => {
		it('shows when document has multiple signers', async () => {
			await updateFile({
				status: FILE_STATUS.DRAFT,
				signers: [
					{ email: 'test1@example.com', signed: [] },
					{ email: 'test2@example.com', signed: [] },
				],
			})

			expect(wrapper.vm.showPreserveOrder).toBe(true)
		})

		it('hides when document has no signers', async () => {
			await updateFile({ signers: [] })

			expect(wrapper.vm.showPreserveOrder).toBe(false)
		})

		it('hides when document has only one signer', async () => {
			await updateFile({ signers: [{ email: 'test@example.com', signed: [] }] })

			expect(wrapper.vm.showPreserveOrder).toBe(false)
		})
	})

	describe('RULE: showViewOrderButton for ordered signatures', () => {
		it('shows when signature flow is ordered_numeric', async () => {
			await updateFile({
				signatureFlow: 'ordered_numeric',
				signers: [
					{ email: 'test1@example.com', signingOrder: 1, signed: [] },
					{ email: 'test2@example.com', signingOrder: 2, signed: [] },
				],
			})

			expect(wrapper.vm.showViewOrderButton).toBe(true)
		})

		it('shows when signature flow is 2 (numeric code)', async () => {
			await updateFile({
				signatureFlow: 2,
				signers: [
					{ email: 'test1@example.com', signingOrder: 1, signed: [] },
					{ email: 'test2@example.com', signingOrder: 2, signed: [] },
				],
			})

			expect(wrapper.vm.showViewOrderButton).toBe(true)
		})

		it('hides when signature flow is parallel', async () => {
			await updateFile({
				signatureFlow: 'parallel',
				signers: [
					{ email: 'test1@example.com', signed: [] },
					{ email: 'test2@example.com', signed: [] },
				],
			})

			expect(wrapper.vm.showViewOrderButton).toBe(false)
		})

		it('hides when only one signer', async () => {
			await updateFile({
				signatureFlow: 'ordered_numeric',
				signers: [{ email: 'test@example.com', signed: [] }],
			})

			expect(wrapper.vm.showViewOrderButton).toBe(false)
		})
	})

	describe('RULE: showSaveButton permission logic', () => {
		it('shows when user can save and has signers', async () => {
			filesStore.canRequestSign = true
			await updateFile({
				status: FILE_STATUS.DRAFT,
				signers: [{ email: 'test@example.com', signed: [] }],
			})

			expect(wrapper.vm.showSaveButton).toBe(true)
		})

		it('hides when user cannot save', async () => {
			await updateFile({
				status: FILE_STATUS.SIGNED,
				signers: [{ email: 'test@example.com', signed: ['sig'] }],
			})

			expect(wrapper.vm.showSaveButton).toBe(false)
		})

		it('hides when no signers', async () => {
			await updateFile({ status: FILE_STATUS.DRAFT, signers: [] })

			expect(wrapper.vm.showSaveButton).toBe(false)
		})
	})

	describe('RULE: showRequestButton permission logic', () => {
		it('shows when user can save and document not requested yet', async () => {
			filesStore.canRequestSign = true
			await updateFile({
				status: FILE_STATUS.DRAFT,
				signatureFlow: 'parallel',
				signers: [{ email: 'test@example.com', signed: [], status: 0 }],
			})

			expect(wrapper.vm.showRequestButton).toBe(true)
		})

		it('hides when document is signed', async () => {
			await updateFile({
				status: FILE_STATUS.SIGNED,
				signers: [{ email: 'test@example.com', signed: ['sig'] }],
			})

			expect(wrapper.vm.showRequestButton).toBe(false)
		})

		it('hides when user cannot save', async () => {
			filesStore.canRequestSign = false
			await updateFile({
				status: FILE_STATUS.DRAFT,
				signers: [{ email: 'test@example.com', signed: [] }],
			})

			expect(wrapper.vm.showRequestButton).toBe(false)
		})
	})

	describe('RULE: showSigningProgress when document active', () => {
		it('shows when signingProgressStatus is SIGNING_IN_PROGRESS', async () => {
			await wrapper.setData({ signingProgressStatus: FILE_STATUS.SIGNING_IN_PROGRESS })

			expect(wrapper.vm.showSigningProgress).toBe(true)
		})

		it('hides when signingProgressStatus is not SIGNING_IN_PROGRESS', async () => {
			await wrapper.setData({ signingProgressStatus: FILE_STATUS.DRAFT })

			expect(wrapper.vm.showSigningProgress).toBe(false)
		})
	})

	describe('RULE: canEditSigningOrder when using ordered flow', () => {
		beforeEach(async () => {
			await updateFile({
				status: FILE_STATUS.DRAFT,
				signatureFlow: 'ordered_numeric',
				signers: [
						{ email: 'test1@example.com', signingOrder: 1 },
						{ email: 'test2@example.com', signingOrder: 2 },
				],
			})
		})

		it('allows editing when flow is ordered_numeric', () => {
			expect(wrapper.vm.canEditSigningOrder(wrapper.vm.filesStore.files[1].signers[0])).toBe(true)
		})

		it('allows editing when flow is 2 (numeric)', async () => {
			await updateFile({ signatureFlow: 2 })

			expect(wrapper.vm.canEditSigningOrder(wrapper.vm.filesStore.files[1].signers[0])).toBe(true)
		})

		it('blocks editing when signer has signed', async () => {
			await updateFile({
				signers: [
					{ ...filesStore.files[1].signers[0], signed: ['signature'] },
					filesStore.files[1].signers[1],
				],
			})

			expect(wrapper.vm.canEditSigningOrder(wrapper.vm.filesStore.files[1].signers[0])).toBe(false)
		})

		it('blocks editing when flow is parallel', async () => {
			await updateFile({ signatureFlow: 'parallel' })

			expect(wrapper.vm.canEditSigningOrder(wrapper.vm.filesStore.files[1].signers[0])).toBe(false)
		})
	})

	describe('RULE: canDelete signer permission', () => {
		it('allows deleting unsigned signer', async () => {
			filesStore.canRequestSign = true
			await updateFile({
				status: FILE_STATUS.DRAFT,
					signers: [{ email: 'test@example.com' }],
			})
				const signer = { email: 'test@example.com' }

			expect(wrapper.vm.canDelete(signer)).toBe(true)
		})

		it('blocks deleting signed signer', () => {
			const signer = { email: 'test@example.com', signed: ['signature'] }

			expect(wrapper.vm.canDelete(signer)).toBe(false)
		})

		it('blocks deleting when array has signature', () => {
			const signer = { email: 'test@example.com', signed: ['sig1', 'sig2'] }

			expect(wrapper.vm.canDelete(signer)).toBe(false)
		})
	})

	describe('RULE: canRequestSignature for individual signer', () => {
		it('allows request when signer unsigned and document able to sign', async () => {
			filesStore.canRequestSign = true
			await updateFile({
				status: FILE_STATUS.ABLE_TO_SIGN,
				signatureFlow: 'parallel',
				signers: [{
					email: 'test@example.com',
					status: 0,
					signRequestId: 10,
				}],
			})
				const signer = { email: 'test@example.com', status: 0, signRequestId: 10 }

			expect(wrapper.vm.canRequestSignature(signer)).toBe(true)
		})

		it('blocks request when signer already signed', async () => {
			filesStore.canRequestSign = true
			await updateFile({
				status: FILE_STATUS.ABLE_TO_SIGN,
				signatureFlow: 'parallel',
				signers: [{
					email: 'test@example.com',
					signed: ['signature'],
					status: 0,
					signRequestId: 10,
				}],
			})
			const signer = { email: 'test@example.com', signed: ['signature'], status: 0, signRequestId: 10 }

			expect(wrapper.vm.canRequestSignature(signer)).toBe(false)
		})

		it('blocks request when document is draft', async () => {
			filesStore.canRequestSign = true
			await updateFile({
				status: FILE_STATUS.DRAFT,
				signatureFlow: 'parallel',
				signers: [{
					email: 'test@example.com',
					signed: [],
					status: 0,
					signRequestId: 10,
				}],
			})
			const signer = { email: 'test@example.com', signed: [], status: 0, signRequestId: 10 }

			expect(wrapper.vm.canRequestSignature(signer)).toBe(false)
		})
	})

	describe('RULE: canSendReminder for pending signers', () => {
		it('allows reminder when signer unsigned and document able to sign', async () => {
			filesStore.canRequestSign = true
			await updateFile({
				status: FILE_STATUS.ABLE_TO_SIGN,
				signatureFlow: 'parallel',
				signers: [{
					email: 'test@example.com',
					status: 1,
					signRequestId: 10,
				}],
			})
				const signer = { email: 'test@example.com', status: 1, signRequestId: 10 }

			expect(wrapper.vm.canSendReminder(signer)).toBe(true)
		})

		it('allows reminder when document partially signed', async () => {
			filesStore.canRequestSign = true
			await updateFile({
				status: FILE_STATUS.PARTIAL_SIGNED,
				signatureFlow: 'parallel',
				signers: [{
					email: 'test@example.com',
					status: 1,
					signRequestId: 10,
				}],
			})
				const signer = { email: 'test@example.com', status: 1, signRequestId: 10 }

			expect(wrapper.vm.canSendReminder(signer)).toBe(true)
		})

		it('blocks reminder when signer already signed', async () => {
			filesStore.canRequestSign = true
			await updateFile({
				status: FILE_STATUS.ABLE_TO_SIGN,
				signatureFlow: 'parallel',
				signers: [{
					email: 'test@example.com',
					signed: ['signature'],
					status: 1,
					signRequestId: 10,
				}],
			})
			const signer = { email: 'test@example.com', signed: ['signature'], status: 1, signRequestId: 10 }

			expect(wrapper.vm.canSendReminder(signer)).toBe(false)
		})

		it('blocks reminder when document is draft', async () => {
			filesStore.canRequestSign = true
			await updateFile({
				status: FILE_STATUS.DRAFT,
				signatureFlow: 'parallel',
				signers: [{
					email: 'test@example.com',
					signed: [],
					status: 1,
					signRequestId: 10,
				}],
			})
			const signer = { email: 'test@example.com', signed: [], status: 1, signRequestId: 10 }

			expect(wrapper.vm.canSendReminder(signer)).toBe(false)
		})
	})

	describe('RULE: canCustomizeMessage permission', () => {
		it('allows customizing message when signer unsigned', async () => {
			await updateMethods([{ name: 'email', enabled: true }])
			await updateFile({
				status: FILE_STATUS.ABLE_TO_SIGN,
				signatureFlow: 'parallel',
				signers: [{
					email: 'test@example.com',
					signRequestId: 10,
					identifyMethods: [{ method: 'email' }],
					status: 0,
				}],
			})
			const signer = {
				email: 'test@example.com',
				signRequestId: 10,
				identifyMethods: [{ method: 'email' }],
				status: 0,
			}

			expect(wrapper.vm.canCustomizeMessage(signer)).toBe(true)
		})

		it('blocks customizing when signer signed', () => {
			const signer = { email: 'test@example.com', signed: ['signature'] }

			expect(wrapper.vm.canCustomizeMessage(signer)).toBe(false)
		})
	})

	describe('RULE: signatureFlow calculation with admin override', () => {
		it('returns ordered_numeric when file flow is 2', async () => {
			await updateFile({ signatureFlow: 2 })
			expect(wrapper.vm.signatureFlow).toBe('ordered_numeric')
		})

		it('returns parallel when file flow is 1', async () => {
			await updateFile({ signatureFlow: 1 })
			expect(wrapper.vm.signatureFlow).toBe('parallel')
		})

		it('returns none when file flow is 0', async () => {
			await updateFile({ signatureFlow: 0 })
			expect(wrapper.vm.signatureFlow).toBe('none')
		})

		it('uses admin flow when file flow is none', async () => {
			await wrapper.setData({ adminSignatureFlow: 'ordered_numeric' })
			await updateFile({ signatureFlow: 'none' })
			expect(wrapper.vm.signatureFlow).toBe('ordered_numeric')
		})

		it('defaults to parallel when both file and admin are none', async () => {
			await wrapper.setData({ adminSignatureFlow: 'none' })
			await updateFile({ signatureFlow: 'none' })
			expect(wrapper.vm.signatureFlow).toBe('parallel')
		})
	})

	describe('RULE: isAdminFlowForced detection', () => {
		it('returns true when admin flow set to ordered_numeric', async () => {
			await wrapper.setData({ adminSignatureFlow: 'ordered_numeric' })
			expect(wrapper.vm.isAdminFlowForced).toBe(true)
		})

		it('returns true when admin flow set to parallel', async () => {
			await wrapper.setData({ adminSignatureFlow: 'parallel' })
			expect(wrapper.vm.isAdminFlowForced).toBe(true)
		})

		it('returns false when admin flow is none', async () => {
			await wrapper.setData({ adminSignatureFlow: 'none' })
			expect(wrapper.vm.isAdminFlowForced).toBe(false)
		})

		it('hides preserve order switch when admin forces flow', async () => {
			await wrapper.setData({ adminSignatureFlow: 'ordered_numeric' })
			await updateFile({
				signers: [
					{ email: 'test1@example.com' },
					{ email: 'test2@example.com' },
				],
			})
			expect(wrapper.vm.showPreserveOrder).toBe(false)
		})
	})

	describe('RULE: canSignerActInOrder for ordered signatures', () => {
		it('allows all signers in parallel flow', async () => {
			await updateMethods([{ name: 'email', enabled: true }])
			await updateFile({
				signatureFlow: 'parallel',
				signers: [
					{ email: 'test1@example.com', signingOrder: 1, identifyMethods: [{ method: 'email' }] },
					{ email: 'test2@example.com', signingOrder: 2, identifyMethods: [{ method: 'email' }] },
				],
			})
			const signer2 = filesStore.files[1].signers[1]
			expect(wrapper.vm.canSignerActInOrder(signer2)).toBe(true)
		})

		it('blocks signer when earlier order pending in ordered_numeric', async () => {
			await updateMethods([{ name: 'email', enabled: true }])
			await updateFile({
				signatureFlow: 'ordered_numeric',
				signers: [
					{ email: 'test1@example.com', signed: [], signingOrder: 1, identifyMethods: [{ method: 'email' }] },
					{ email: 'test2@example.com', signed: [], signingOrder: 2, identifyMethods: [{ method: 'email' }] },
				],
			})
			const signer2 = filesStore.files[1].signers[1]
			expect(wrapper.vm.canSignerActInOrder(signer2)).toBe(false)
		})

		it('allows signer when earlier signers signed in ordered_numeric', async () => {
			await updateMethods([{ name: 'email', enabled: true }])
			await updateFile({
				signatureFlow: 'ordered_numeric',
				signers: [
					{ email: 'test1@example.com', signed: ['sig'], signingOrder: 1, identifyMethods: [{ method: 'email' }] },
					{ email: 'test2@example.com', signed: [], signingOrder: 2, identifyMethods: [{ method: 'email' }] },
				],
			})
			const signer2 = filesStore.files[1].signers[1]
			expect(wrapper.vm.canSignerActInOrder(signer2)).toBe(true)
		})

		it('blocks signer with disabled method', async () => {
			await updateMethods([{ name: 'sms', enabled: false }])
			await updateFile({
				signatureFlow: 'parallel',
				signers: [
					{ email: 'test1@example.com', identifyMethods: [{ method: 'sms' }] },
				],
			})
			const signer = filesStore.files[1].signers[0]
			expect(wrapper.vm.canSignerActInOrder(signer)).toBe(false)
		})
	})

	describe('RULE: hasDraftSigners detection for ordered_numeric', () => {
		it('detects draft signers at current order', async () => {
			await updateFile({
				signatureFlow: 'ordered_numeric',
				signers: [
					{ email: 'signer1@example.com', signed: [], signingOrder: 1, status: 0 },
					{ email: 'signer2@example.com', signed: [], signingOrder: 2, status: 0 },
				],
			})
			expect(wrapper.vm.hasDraftSigners).toBe(true)
		})

		it('returns false when all signers are pending (status=1)', async () => {
			await updateFile({
				signatureFlow: 'ordered_numeric',
				signers: [
					{ email: 'signer1@example.com', signed: [], signingOrder: 1, status: 1 },
					{ email: 'signer2@example.com', signed: [], signingOrder: 2, status: 1 },
				],
			})
			expect(wrapper.vm.hasDraftSigners).toBe(false)
		})

		it('detects draft at order 2 after order 1 signed', async () => {
			await updateFile({
				signatureFlow: 'ordered_numeric',
				signers: [
					{ email: 'signer1@example.com', signed: ['sig'], signingOrder: 1, status: 2 },
					{ email: 'signer2@example.com', signed: [], signingOrder: 2, status: 0 },
				],
			})
			expect(wrapper.vm.hasDraftSigners).toBe(true)
		})

		it('uses any draft logic for parallel flow', async () => {
			await updateFile({
				signatureFlow: 'parallel',
				signers: [
					{ email: 'signer1@example.com', signed: [], status: 0 },
					{ email: 'signer2@example.com', signed: [], status: 1 },
				],
			})
			expect(wrapper.vm.hasDraftSigners).toBe(true)
		})
	})

	describe('RULE: hasSignersWithDisabledMethods warning', () => {
		it('detects signers using disabled methods', async () => {
			await updateMethods([{ name: 'sms', enabled: false }])
			await updateFile({
				signers: [
					{ email: 'test1@example.com', signed: [], identifyMethods: [{ method: 'sms' }] },
				],
			})
			expect(wrapper.vm.hasSignersWithDisabledMethods).toBe(true)
		})

		it('returns false when all methods enabled', async () => {
			await updateMethods([{ name: 'email', enabled: true }])
			await updateFile({
				signers: [
					{ email: 'test1@example.com', signed: [], identifyMethods: [{ method: 'email' }] },
				],
			})
			expect(wrapper.vm.hasSignersWithDisabledMethods).toBe(false)
		})

		it('ignores signed signers when checking disabled methods', async () => {
			await updateMethods([{ name: 'sms', enabled: false }])
			await updateFile({
				signers: [
					{ email: 'test1@example.com', signed: ['sig'], identifyMethods: [{ method: 'sms' }] },
				],
			})
			expect(wrapper.vm.hasSignersWithDisabledMethods).toBe(false)
		})

		it('hides save button when has signers with disabled methods', async () => {
			await updateMethods([{ name: 'sms', enabled: false }])
			filesStore.canRequestSign = true
			await updateFile({
				status: FILE_STATUS.DRAFT,
				signers: [
					{ email: 'test1@example.com', signed: [], identifyMethods: [{ method: 'sms' }] },
				],
			})
			expect(wrapper.vm.showSaveButton).toBe(false)
		})
	})

	describe('RULE: onPreserveOrderChange synchronization', () => {
		it('sets ordered_numeric flow when enabling', async () => {
			await updateFile({
				signatureFlow: 'parallel',
				signers: [
					{ email: 'signer1@example.com', signed: [] },
				],
			})
			wrapper.vm.onPreserveOrderChange(true)
			await wrapper.vm.$nextTick()
			expect(filesStore.files[1].signatureFlow).toBe('ordered_numeric')
		})

		it('assigns sequential orders when enabling', async () => {
			await updateFile({
				signatureFlow: 'parallel',
				signers: [
					{ email: 'signer1@example.com', signed: [] },
					{ email: 'signer2@example.com', signed: [] },
				],
			})
			wrapper.vm.onPreserveOrderChange(true)
			await wrapper.vm.$nextTick()
			expect(filesStore.files[1].signers[0].signingOrder).toBe(1)
			expect(filesStore.files[1].signers[1].signingOrder).toBe(2)
		})

		it('reverts to parallel when disabling', async () => {
			await wrapper.setData({ adminSignatureFlow: 'none' })
			await updateFile({
				signatureFlow: 'ordered_numeric',
				signers: [
					{ email: 'signer1@example.com', signed: [], signingOrder: 1 },
				],
			})
			wrapper.vm.onPreserveOrderChange(false)
			await wrapper.vm.$nextTick()
			expect(filesStore.files[1].signatureFlow).toBe('parallel')
		})

		it('preserves admin flow when disabling user preference', async () => {
			await wrapper.setData({ adminSignatureFlow: 'ordered_numeric' })
			await updateFile({
				signatureFlow: 'ordered_numeric',
				signers: [
					{ email: 'signer1@example.com', signed: [], signingOrder: 1 },
				],
			})
			wrapper.vm.onPreserveOrderChange(false)
			await wrapper.vm.$nextTick()
			expect(filesStore.files[1].signatureFlow).toBe('ordered_numeric')
		})
	})

	describe('RULE: syncPreserveOrderWithFile on file change', () => {
		it('enables preserve order for ordered_numeric flow', async () => {
			await updateFile({ signatureFlow: 'ordered_numeric' })
			wrapper.vm.syncPreserveOrderWithFile()
			expect(wrapper.vm.preserveOrder).toBe(true)
		})

		it('enables preserve order for numeric flow 2', async () => {
			await updateFile({ signatureFlow: 2 })
			wrapper.vm.syncPreserveOrderWithFile()
			expect(wrapper.vm.preserveOrder).toBe(true)
		})

		it('disables preserve order for parallel flow', async () => {
			await updateFile({ signatureFlow: 'parallel' })
			wrapper.vm.syncPreserveOrderWithFile()
			expect(wrapper.vm.preserveOrder).toBe(false)
		})

		it('disables preserve order when admin forces flow', async () => {
			await wrapper.setData({ adminSignatureFlow: 'ordered_numeric' })
			await updateFile({ signatureFlow: 'ordered_numeric' })
			wrapper.vm.syncPreserveOrderWithFile()
			expect(wrapper.vm.preserveOrder).toBe(false)
		})
	})

	describe('RULE: updateSigningOrder and sort signers', () => {
		it('updates signer order and sorts', async () => {
			await updateFile({
				signers: [
					{ email: 'signer1@example.com', signingOrder: 2, identify: 'signer1@example.com' },
					{ email: 'signer2@example.com', signingOrder: 3, identify: 'signer2@example.com' },
				],
			})
			const signer2 = filesStore.files[1].signers[1]
			wrapper.vm.updateSigningOrder(signer2, '1')
			await wrapper.vm.$nextTick()
			expect(filesStore.files[1].signers[0].identify).toBe('signer2@example.com')
		})

		it('ignores invalid order values', async () => {
			await updateFile({
				signers: [
					{ email: 'signer1@example.com', signingOrder: 1, identify: 'signer1@example.com' },
				],
			})
			const signer1 = filesStore.files[1].signers[0]
			wrapper.vm.updateSigningOrder(signer1, 'invalid')
			await wrapper.vm.$nextTick()
			expect(filesStore.files[1].signers[0].signingOrder).toBe(1)
		})

		it('handles signer not found gracefully', async () => {
			await updateFile({
				signers: [
					{ email: 'signer1@example.com', signingOrder: 1, identify: 'signer1@example.com' },
				],
			})
			const fakeSigner = { identify: 'nonexistent@example.com' }
			wrapper.vm.updateSigningOrder(fakeSigner, '2')
			await wrapper.vm.$nextTick()
			expect(filesStore.files[1].signers).toHaveLength(1)
		})
	})

	describe('RULE: enabledMethods filter for modal', () => {
		it('shows all enabled methods when adding new signer', async () => {
			await updateMethods([
				{ name: 'email', enabled: true, friendly_name: 'Email' },
				{ name: 'sms', enabled: true, friendly_name: 'SMS' },
				{ name: 'account', enabled: false, friendly_name: 'Account' },
			])
			await wrapper.setData({ signerToEdit: {} })
			expect(wrapper.vm.enabledMethods).toHaveLength(2)
			expect(wrapper.vm.enabledMethods.map((m: { name: string }) => m.name)).toContain('email')
			expect(wrapper.vm.enabledMethods.map((m: { name: string }) => m.name)).toContain('sms')
		})

		it('shows only signer method when editing even if disabled', async () => {
			await updateMethods([
				{ name: 'email', enabled: true, friendly_name: 'Email' },
				{ name: 'sms', enabled: false, friendly_name: 'SMS' },
			])
			await wrapper.setData({
				signerToEdit: {
					identify: 'test@example.com',
					identifyMethods: [{ method: 'sms' }],
				},
			})
			expect(wrapper.vm.enabledMethods).toHaveLength(1)
			expect(wrapper.vm.enabledMethods[0].name).toBe('sms')
		})

		it('detects disabled method for edited signer', async () => {
			await updateMethods([{ name: 'sms', enabled: false }])
			await wrapper.setData({
				signerToEdit: {
					identify: 'test@example.com',
					identifyMethods: [{ method: 'sms' }],
				},
			})
			expect(wrapper.vm.isSignerMethodDisabled).toBe(true)
		})
	})
})
