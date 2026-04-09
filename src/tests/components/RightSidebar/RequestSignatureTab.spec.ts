/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, shallowMount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import type { useFilesStore as useFilesStoreType } from '../../../store/files.js'
import { usePoliciesStore } from '../../../store/policies'
import RequestSignatureTab from '../../../components/RightSidebar/RequestSignatureTab.vue'
import { useFilesStore } from '../../../store/files.js'
import { FILE_STATUS } from '../../../constants.js'

const { generateUrlMock } = vi.hoisted(() => ({
	generateUrlMock: vi.fn((path: string, params?: Record<string, string | number>) => {
		if (!params) {
			return path
		}

		return Object.entries(params).reduce((url, [key, value]) => {
			return url.replace(`{${key}}`, String(value))
		}, path)
	}),
}))

// Mock translation function
;(globalThis as typeof globalThis & { t: (app: string, msg: string) => string }).t = vi.fn((app: string, msg: string) => msg)

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => {
		if (key === 'config') {
			return {
				'sign-elements': { 'is-available': true },
				'identification_documents': { enabled: false },
			}
		}
		if (key === 'can_request_sign') return true
		if (key === 'effective_policies') {
			return {
				policies: {
					signature_flow: {
						policyKey: 'signature_flow',
						effectiveValue: 'none',
						sourceScope: 'system',
						visible: true,
						editableByCurrentActor: true,
						allowedValues: ['none', 'parallel', 'ordered_numeric'],
						canSaveAsUserDefault: true,
						canUseAsRequestOverride: true,
						preferenceWasCleared: false,
						blockedBy: null,
					},
				},
			}
		}
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
vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
		put: vi.fn(),
		delete: vi.fn(),
		patch: vi.fn(),
	},
}))
vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path) => `/ocs${path}`),
	generateUrl: (...args: Parameters<typeof generateUrlMock>) => generateUrlMock(...args),
	getRootUrl: vi.fn(() => ''),
}))

vi.mock('@libresign/pdf-elements', () => ({
	setWorkerPath: vi.fn(),
}))

describe('RequestSignatureTab - Critical Business Rules', () => {
	let wrapper: VueWrapper<any>
	let filesStore: ReturnType<typeof useFilesStoreType>

	const createEffectivePoliciesResponse = (policyOverrides: Record<string, unknown> = {}) => ({
		data: {
			ocs: {
				data: {
					policies: {
						signature_flow: {
							policyKey: 'signature_flow',
							effectiveValue: 'none',
							sourceScope: 'system',
							visible: true,
							editableByCurrentActor: true,
							allowedValues: ['none', 'parallel', 'ordered_numeric'],
							canSaveAsUserDefault: true,
							canUseAsRequestOverride: true,
							preferenceWasCleared: false,
							blockedBy: null,
							...policyOverrides,
						},
					},
				},
			},
		},
	})

	const createSignatureFlowPolicy = (policyOverrides: Record<string, unknown> = {}) => {
		return createEffectivePoliciesResponse(policyOverrides).data.ocs.data.policies.signature_flow
	}

	const updateFile = async (patch: Record<string, unknown>) => {
		const current = filesStore.files[1] || { id: 1 }
		const hasSigners = Object.prototype.hasOwnProperty.call(patch, 'signers')
		await filesStore.addFile({
			...current,
			...patch,
			id: 1,
			detailsLoaded: hasSigners ? true : current.detailsLoaded,
		})
		await wrapper.vm.$nextTick()
	}
	const setVmState = async (patch: Record<string, unknown>) => {
		Object.entries(patch).forEach(([key, value]) => {
			;(wrapper.vm as unknown as Record<string, unknown>)[key] = value
		})
		await wrapper.vm.$nextTick()
	}
	const updateMethods = async (methods: unknown[]) => {
		await setVmState({ methods })
	}
	const updatePolicies = async (policyOverrides: Record<string, unknown>) => {
		const policiesStore = usePoliciesStore()
		policiesStore.setPolicies({
			signature_flow: createSignatureFlowPolicy(policyOverrides),
		})
		await wrapper.vm.$nextTick()
	}

	beforeEach(async () => {
		setActivePinia(createPinia())
		generateUrlMock.mockClear()
		vi.mocked(axios.get).mockImplementation(async (url: string) => {
			if (url.includes('/apps/libresign/api/v1/policies/effective')) {
				return createEffectivePoliciesResponse() as Awaited<ReturnType<typeof axios.get>>
			}

			return { data: { ocs: { data: null } } } as Awaited<ReturnType<typeof axios.get>>
		})
		filesStore = useFilesStore()

		await filesStore.addFile({
			id: 1,
			name: 'test.pdf',
			status: FILE_STATUS.DRAFT,
			signers: [],
			detailsLoaded: true,
			nodeType: 'file',
		})
		filesStore.selectFile(1)
		filesStore.canRequestSign = true

		wrapper = shallowMount(RequestSignatureTab, {
			mocks: {
				t: (_app: string, text: string) => text,
			},
			global: {
				stubs: {
					EnvelopeFilesList: { name: 'EnvelopeFilesList', template: '<div><slot /></div>' },
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
		}) as VueWrapper<any>
		await flushPromises()
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

	describe('RULE: showRememberSignatureFlow only when signing order is meaningful', () => {
		it('shows when document has multiple signers and user can save preference', async () => {
			await updateFile({
				status: FILE_STATUS.DRAFT,
				signers: [
					{ email: 'test1@example.com', signed: [] },
					{ email: 'test2@example.com', signed: [] },
				],
			})

			expect(wrapper.vm.showRememberSignatureFlow).toBe(true)
		})

		it('hides when document has only one signer', async () => {
			await updateFile({
				status: FILE_STATUS.DRAFT,
				signers: [{ email: 'test@example.com', signed: [] }],
			})

			expect(wrapper.vm.showRememberSignatureFlow).toBe(false)
		})

		it('hides when user cannot save preference even with multiple signers', async () => {
			await updateFile({
				status: FILE_STATUS.DRAFT,
				signers: [
					{ email: 'test1@example.com', signed: [] },
					{ email: 'test2@example.com', signed: [] },
				],
			})
			await updatePolicies({ canSaveAsUserDefault: false })

			expect(wrapper.vm.showRememberSignatureFlow).toBe(false)
		})

		it('hides when effective signature flow policy is fixed to parallel', async () => {
			await updateFile({
				status: FILE_STATUS.DRAFT,
				signers: [
					{ email: 'test1@example.com', signed: [] },
					{ email: 'test2@example.com', signed: [] },
				],
			})
			await updatePolicies({ effectiveValue: 'parallel', canUseAsRequestOverride: false })

			expect(wrapper.vm.showPreserveOrder).toBe(false)
			expect(wrapper.vm.showRememberSignatureFlow).toBe(false)
		})

		it('hides when effective signature flow policy is fixed to ordered_numeric', async () => {
			await updateFile({
				status: FILE_STATUS.DRAFT,
				signers: [
					{ email: 'test1@example.com', signed: [] },
					{ email: 'test2@example.com', signed: [] },
				],
			})
			await updatePolicies({ effectiveValue: 'ordered_numeric', canUseAsRequestOverride: false })

			expect(wrapper.vm.showPreserveOrder).toBe(false)
			expect(wrapper.vm.showRememberSignatureFlow).toBe(false)
		})

		it('keeps toggles available when ordered_numeric is only the default and request overrides are still allowed', async () => {
			await updateFile({
				status: FILE_STATUS.DRAFT,
				signers: [
					{ email: 'test1@example.com', signed: [] },
					{ email: 'test2@example.com', signed: [] },
				],
			})
			await updatePolicies({ effectiveValue: 'ordered_numeric', canUseAsRequestOverride: true })

			expect(wrapper.vm.showPreserveOrder).toBe(true)
			expect(wrapper.vm.showRememberSignatureFlow).toBe(true)
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
			await setVmState({ signingProgressStatus: FILE_STATUS.SIGNING_IN_PROGRESS })

			expect(wrapper.vm.showSigningProgress).toBe(true)
		})

		it('hides when signingProgressStatus is not SIGNING_IN_PROGRESS', async () => {
			await setVmState({ signingProgressStatus: FILE_STATUS.DRAFT })

			expect(wrapper.vm.showSigningProgress).toBe(false)
		})
	})

	describe('RULE: modal navigation uses absolute generated URLs', () => {
		it('uses generateUrl for validation modal links', async () => {
			await wrapper.setProps({ useModal: true })
			await updateFile({ uuid: 'validation-uuid' })

			wrapper.vm.validationFile()

			expect(generateUrlMock).toHaveBeenCalledWith('/apps/libresign/p/validation/{uuid}', { uuid: 'validation-uuid' })
			expect(wrapper.vm.modalSrc).toBe('/apps/libresign/p/validation/validation-uuid')
		})

		it('uses generateUrl for signing modal links', async () => {
			await wrapper.setProps({ useModal: true })
			await updateFile({ signers: [{ me: true, sign_request_uuid: 'sign-uuid' }] })

			await wrapper.vm.sign()

			expect(generateUrlMock).toHaveBeenCalledWith('/apps/libresign/p/sign/{uuid}/pdf', { uuid: 'sign-uuid' })
			expect(wrapper.vm.modalSrc).toBe('/apps/libresign/p/sign/sign-uuid/pdf')
		})

		it('uses the file uuid for approver signing modal links', async () => {
			await wrapper.setProps({ useModal: true })
			await updateFile({
				uuid: 'approver-file-uuid',
				signers: [],
				settings: { isApprover: true },
			})
			generateUrlMock.mockClear()

			await wrapper.vm.sign()

			expect(generateUrlMock).toHaveBeenCalledWith('/apps/libresign/p/sign/{uuid}/pdf', { uuid: 'approver-file-uuid' })
			expect(wrapper.vm.modalSrc).toBe('/apps/libresign/p/sign/approver-file-uuid/pdf')
		})

		it('uses the current signer sign_request_uuid when signing root fields are absent', async () => {
			await wrapper.setProps({ useModal: true })
			await updateFile({
				signers: [{ me: true, sign_request_uuid: 'signer-uuid-123' }],
			})
			generateUrlMock.mockClear()

			await wrapper.vm.sign()

			expect(generateUrlMock).toHaveBeenCalledWith('/apps/libresign/p/sign/{uuid}/pdf', { uuid: 'signer-uuid-123' })
			expect(wrapper.vm.modalSrc).toBe('/apps/libresign/p/sign/signer-uuid-123/pdf')
		})

		it('does not use stale sign_request_uuid from initial state when file has no signing UUIDs', async () => {
			vi.mocked(loadState).mockImplementation((_app: string, key: string, defaultValue: unknown) => {
				if (key === 'sign_request_uuid') {
					return 'stale-sign-request-uuid'
				}
				if (key === 'config') {
					return {
						'sign-elements': { 'is-available': true },
						'identification_documents': { enabled: false },
					}
				}
				if (key === 'can_request_sign') return true
				return defaultValue
			})

			await wrapper.setProps({ useModal: true })
			await updateFile({
				signers: [],
				settings: { isApprover: false },
			})
			generateUrlMock.mockClear()

			await wrapper.vm.sign()

			expect(generateUrlMock).not.toHaveBeenCalledWith('/apps/libresign/p/sign/{uuid}/pdf', { uuid: 'stale-sign-request-uuid' })
			expect(wrapper.vm.modalSrc).toBe('')
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
			expect(wrapper.vm.canEditSigningOrder(wrapper.vm.filesStore.files[1]!.signers[0]!)).toBe(true)
		})

		it('allows editing when flow is 2 (numeric)', async () => {
			await updateFile({ signatureFlow: 2 })

			expect(wrapper.vm.canEditSigningOrder(wrapper.vm.filesStore.files[1]!.signers[0]!)).toBe(true)
		})

		it('blocks editing when signer has signed', async () => {
			await updateFile({
				signers: [
					{ ...filesStore.files[1]!.signers![0]!, signed: ['signature'] },
					filesStore.files[1]!.signers![1]!,
				],
			})

			expect(wrapper.vm.canEditSigningOrder(wrapper.vm.filesStore.files[1]!.signers[0]!)).toBe(false)
		})

		it('blocks editing when flow is parallel', async () => {
			await updateFile({ signatureFlow: 'parallel' })

			expect(wrapper.vm.canEditSigningOrder(wrapper.vm.filesStore.files[1]!.signers[0]!)).toBe(false)
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

	describe('RULE: signatureFlow calculation with effective policy bootstrap', () => {
		it('refreshes policy state from effective policies endpoint on mount', async () => {
			wrapper.unmount()
			vi.mocked(axios.get).mockImplementation(async (url: string) => {
				if (url.includes('/apps/libresign/api/v1/policies/effective')) {
					return createEffectivePoliciesResponse({
						effectiveValue: 'ordered_numeric',
						sourceScope: 'group',
						canUseAsRequestOverride: false,
						blockedBy: 'group',
					}) as Awaited<ReturnType<typeof axios.get>>
				}

				return { data: { ocs: { data: null } } } as Awaited<ReturnType<typeof axios.get>>
			})

			wrapper = shallowMount(RequestSignatureTab, {
				mocks: {
					t: (_app: string, text: string) => text,
				},
				global: {
					stubs: {
						EnvelopeFilesList: { name: 'EnvelopeFilesList', template: '<div><slot /></div>' },
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
			}) as VueWrapper<any>

			await flushPromises()

			expect(wrapper.vm.signatureFlowPolicy.effectiveValue).toBe('ordered_numeric')
			expect(wrapper.vm.signatureFlowPolicy.sourceScope).toBe('group')
			expect(wrapper.vm.signatureFlowPolicy.canUseAsRequestOverride).toBe(false)
		})

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

		it('uses effective policy when file flow is none', async () => {
			await updatePolicies({ effectiveValue: 'ordered_numeric' })
			await updateFile({ signatureFlow: 'none' })
			expect(wrapper.vm.signatureFlow).toBe('ordered_numeric')
		})

		it('uses fixed effective policy even when file flow was parallel', async () => {
			await updatePolicies({ effectiveValue: 'ordered_numeric', canUseAsRequestOverride: false })
			await updateFile({ signatureFlow: 'parallel' })
			expect(wrapper.vm.signatureFlow).toBe('ordered_numeric')
		})

		it('uses fixed effective policy when value comes as object with flow', async () => {
			await updatePolicies({ effectiveValue: { flow: 'ordered_numeric' }, canUseAsRequestOverride: false })
			await updateFile({ signatureFlow: 'parallel' })
			expect(wrapper.vm.signatureFlow).toBe('ordered_numeric')
		})

		it('keeps request-level file flow when policy defaults to ordered_numeric but still allows overrides', async () => {
			await updatePolicies({ effectiveValue: 'ordered_numeric', canUseAsRequestOverride: true })
			await updateFile({ signatureFlow: 'parallel' })
			expect(wrapper.vm.signatureFlow).toBe('parallel')
		})

		it('defaults to parallel when both file and policy are none', async () => {
			await updatePolicies({ effectiveValue: 'none' })
			await updateFile({ signatureFlow: 'none' })
			expect(wrapper.vm.signatureFlow).toBe('parallel')
		})
	})

	describe('RULE: isAdminFlowForced detection', () => {
		it('returns true when policy blocks request overrides', async () => {
			await updatePolicies({ canUseAsRequestOverride: false })
			expect(wrapper.vm.isAdminFlowForced).toBe(true)
		})

		it('returns false when policy allows request overrides', async () => {
			await updatePolicies({ canUseAsRequestOverride: true })
			expect(wrapper.vm.isAdminFlowForced).toBe(false)
		})

		it('hides preserve order switch when policy forces flow', async () => {
			await updatePolicies({
				canUseAsRequestOverride: false,
				effectiveValue: 'ordered_numeric',
			})
			await updateFile({
				signers: [
					{ email: 'test1@example.com' },
					{ email: 'test2@example.com' },
				],
			})
			expect(wrapper.vm.showPreserveOrder).toBe(false)
		})

		it('hides preserve order switch when fixed policy comes as object with flow', async () => {
			await updatePolicies({
				canUseAsRequestOverride: false,
				effectiveValue: { flow: 'ordered_numeric' },
			})
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
			const signer2 = filesStore.files[1]!.signers![1]!
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
			const signer2 = filesStore.files[1]!.signers![1]!
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
			const signer2 = filesStore.files[1]!.signers![1]!
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
			const signer = filesStore.files[1]!.signers![0]!
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

			it('persists user preference when remember choice is enabled', async () => {
				vi.mocked(axios.put).mockResolvedValue({
					data: {
						ocs: {
							data: {
								policy: createSignatureFlowPolicy({
									effectiveValue: 'ordered_numeric',
									sourceScope: 'user',
									canSaveAsUserDefault: true,
									canUseAsRequestOverride: true,
								}),
							},
						},
					},
				})
				await updatePolicies({
					canSaveAsUserDefault: true,
					canUseAsRequestOverride: true,
				})
				await updateFile({
					signatureFlow: 'parallel',
					signers: [
						{ email: 'signer1@example.com', signed: [] },
						{ email: 'signer2@example.com', signed: [] },
					],
				})

				wrapper.vm.rememberSignatureFlow = true
				wrapper.vm.onPreserveOrderChange(true)
				await flushPromises()

				expect(axios.put).toHaveBeenCalledWith(
					'/ocs/apps/libresign/api/v1/policies/user/signature_flow',
					{ value: 'ordered_numeric' },
				)
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
			expect(filesStore.files[1]!.signers![0]!.signingOrder).toBe(1)
			expect(filesStore.files[1]!.signers![1]!.signingOrder).toBe(2)
		})

		it('reassigns sequential orders when all signers share the same signingOrder', async () => {
			// Signers saved via the API return signingOrder: 1 as default for all of them.
			// The old check (!signer.signingOrder) would skip them because !1 === false,
			// leaving both at order 1 and causing the backend to notify both simultaneously.
			await updateFile({
				signatureFlow: 'parallel',
				signers: [
					{ email: 'signer1@example.com', signed: [], signingOrder: 1 },
					{ email: 'signer2@example.com', signed: [], signingOrder: 1 },
				],
			})
			wrapper.vm.onPreserveOrderChange(true)
			await wrapper.vm.$nextTick()
			expect(filesStore.files[1]!.signers![0]!.signingOrder).toBe(1)
			expect(filesStore.files[1]!.signers![1]!.signingOrder).toBe(2)
		})

		it('reverts to parallel when disabling', async () => {
			await updatePolicies({
				effectiveValue: 'none',
				canUseAsRequestOverride: true,
			})
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
			await updatePolicies({
				effectiveValue: 'ordered_numeric',
				canUseAsRequestOverride: false,
			})
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
			await updatePolicies({
				effectiveValue: 'ordered_numeric',
				canUseAsRequestOverride: false,
			})
			await updateFile({ signatureFlow: 'ordered_numeric' })
			wrapper.vm.syncPreserveOrderWithFile()
			expect(wrapper.vm.preserveOrder).toBe(false)
		})

		it('synchronizes stale draft flow and signing orders when policy locks ordered_numeric', async () => {
			await updatePolicies({
				effectiveValue: 'ordered_numeric',
				canUseAsRequestOverride: false,
			})
			await updateFile({
				signatureFlow: 'parallel',
				signers: [
					{ email: 'signer1@example.com', signed: [], signingOrder: 1 },
					{ email: 'signer2@example.com', signed: [], signingOrder: 1 },
				],
			})

			wrapper.vm.syncFileSignatureFlowWithPolicy()

			const syncedFile = filesStore.getEditableFile()
			expect(syncedFile.signatureFlow).toBe('ordered_numeric')
			expect(syncedFile.signers?.[0]?.signingOrder).toBe(1)
			expect(syncedFile.signers?.[1]?.signingOrder).toBe(2)
		})
	})

	describe('RULE: updateSigningOrder and sort signers', () => {
		it('updates signer order and sorts', async () => {
			await updateFile({
				signers: [
					{ email: 'signer1@example.com', signRequestId: 1, signingOrder: 2 },
					{ email: 'signer2@example.com', signRequestId: 2, signingOrder: 3 },
				],
			})
			const signer2 = filesStore.files[1]!.signers![1]!
			wrapper.vm.updateSigningOrder(signer2, '1')
			await wrapper.vm.$nextTick()
			expect(filesStore.files[1]!.signers![0]!.signRequestId).toBe(2)
		})

		it('ignores invalid order values', async () => {
			await updateFile({
				signers: [
					{ email: 'signer1@example.com', signRequestId: 1, signingOrder: 1 },
				],
			})
			const signer1 = filesStore.files[1]!.signers![0]!
			wrapper.vm.updateSigningOrder(signer1, 'invalid')
			await wrapper.vm.$nextTick()
			expect(filesStore.files[1]!.signers![0]!.signingOrder).toBe(1)
		})

		it('handles signer not found gracefully', async () => {
			await updateFile({
				signers: [
					{ email: 'signer1@example.com', signRequestId: 1, signingOrder: 1 },
				],
			})
			const fakeSigner = { localKey: 'signer:999' }
			wrapper.vm.updateSigningOrder(fakeSigner, '2')
			await wrapper.vm.$nextTick()
			expect(filesStore.files[1].signers).toHaveLength(1)
		})
	})

	describe('RULE: enabledMethods filter for modal', () => {
		it('does not propagate legacy signRequestId into signerToEdit', async () => {
			await updateFile({
				signers: [
					{ email: 'signer1@example.com', displayName: 'Signer 1', signRequestId: 42, identifyMethods: [{ method: 'email', value: 'signer1@example.com' }] },
				],
			})

			const signer = filesStore.files[1]!.signers![0]!
			wrapper.vm.editSigner(signer)

			expect(wrapper.vm.signerToEdit.identify).toBeUndefined()
			expect(wrapper.vm.signerToEdit.identifyMethods).toEqual([{ method: 'email', value: 'signer1@example.com' }])
			expect('signRequestId' in wrapper.vm.signerToEdit).toBe(false)
		})

		it('shows all enabled methods when adding new signer', async () => {
			await updateMethods([
				{ name: 'email', enabled: true, friendly_name: 'Email' },
				{ name: 'sms', enabled: true, friendly_name: 'SMS' },
				{ name: 'account', enabled: false, friendly_name: 'Account' },
			])
			await setVmState({ signerToEdit: {} })
			expect(wrapper.vm.enabledMethods).toHaveLength(2)
			expect(wrapper.vm.enabledMethods.map((m: { name: string }) => m.name)).toContain('email')
			expect(wrapper.vm.enabledMethods.map((m: { name: string }) => m.name)).toContain('sms')
		})

		it('shows only signer method when editing even if disabled', async () => {
			await updateMethods([
				{ name: 'email', enabled: true, friendly_name: 'Email' },
				{ name: 'sms', enabled: false, friendly_name: 'SMS' },
			])
			await setVmState({
				signerToEdit: {
					identifyMethods: [{ method: 'sms' }],
				},
			})
			expect(wrapper.vm.enabledMethods).toHaveLength(1)
			expect(wrapper.vm.enabledMethods[0].name).toBe('sms')
		})

		it('detects disabled method for edited signer', async () => {
			await updateMethods([{ name: 'sms', enabled: false }])
			await setVmState({
				signerToEdit: {
					identifyMethods: [{ method: 'sms' }],
				},
			})
			expect(wrapper.vm.isSignerMethodDisabled).toBe(true)
		})
	})
})
