/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
let RequestSignatureTab
import { FILE_STATUS } from '../../constants.js'

// Mock translation function
global.t = vi.fn((app, msg) => msg)

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

vi.mock('@libresign/pdf-elements/src/utils/asyncReader.js', () => ({
	setWorkerPath: vi.fn(),
}))

describe('RequestSignatureTab - Critical Business Rules', () => {
	let wrapper
	let filesStore
	const updateFile = async (patch) => {
		const current = filesStore.files[1] || { id: 1 }
		await filesStore.addFile({ ...current, ...patch, id: 1 })
		await wrapper.vm.$nextTick()
	}
	const updateMethods = async (methods) => {
		await wrapper.setData({ methods })
	}

	beforeEach(async () => {
		setActivePinia(createPinia())
		RequestSignatureTab = (await import('./RequestSignatureTab.vue')).default
		const { useFilesStore } = await import('../../store/files.js')
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
			global: {
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
})
