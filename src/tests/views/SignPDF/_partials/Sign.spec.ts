/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import { DOMWrapper, flushPromises, mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import { FILE_STATUS, SIGN_REQUEST_STATUS } from '../../../../constants.js'
import { useSignMethodsStore } from '../../../../store/signMethods.js'
import { useSignStore } from '../../../../store/sign.js'
import Sign from '../../../../views/SignPDF/_partials/Sign.vue'

const currentRoute = vi.hoisted(() => ({
	name: 'SignPDF',
	params: { uuid: '' },
	query: {} as Record<string, string>,
	path: '',
}))

vi.mock('vue-router', async (importOriginal) => ({
	...await importOriginal<typeof import('vue-router')>(),
	useRoute: () => currentRoute,
}))

vi.mock('@nextcloud/axios', () => {
	const response = { data: { ocs: { data: { elements: [] } } } }
	return {
		default: Object.assign(vi.fn().mockResolvedValue(response), {
			get: vi.fn().mockResolvedValue(response),
			post: vi.fn().mockResolvedValue(response),
		}),
	}
})

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: vi.fn(() => null) }))
vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app, _key, defaultValue) => defaultValue),
}))
vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => `/ocs/v2.php/apps/libresign${path}`),
}))
vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))
vi.mock('@nextcloud/capabilities', () => ({
	getCapabilities: vi.fn(() => ({
		libresign: {
			config: {
				'sign-elements': { 'can-create-signature': true },
			},
		},
	})),
}))
vi.mock('vue-select', () => ({
	default: {
		name: 'VSelect',
		props: ['modelValue'],
		emits: ['update:modelValue'],
		render: () => null,
	},
}))

// NcDialog uses a focus trap inside a body teleport. The real dialog is
// rendered in these tests, but DOM focus management is outside their scope.
vi.mock('focus-trap', async (importOriginal) => ({
	...await importOriginal<typeof import('focus-trap')>(),
	createFocusTrap: vi.fn(() => ({
		activate: vi.fn(),
		deactivate: vi.fn(),
		updateContainerElements: vi.fn(),
	})),
}))

describe('Sign.vue approval labels and signing methods', () => {
	let wrapper: VueWrapper | undefined

	afterEach(() => {
		wrapper?.unmount()
		wrapper = undefined
		document.body.replaceChildren()
	})

	it.each([
		{
			method: 'clickToSign',
			approval: true,
			confirmation: 'Confirm that you have reviewed this document and want to sign it to record your approval.',
		},
		{
			method: 'clickToSign',
			approval: false,
			confirmation: 'Confirm that you want to sign this document.',
		},
		{
			method: 'password',
			approval: true,
			confirmation: 'Enter your signature password to sign and approve the document.',
		},
		{
			method: 'password',
			approval: false,
			confirmation: 'Enter your signature password to sign the document.',
		},
	] as const)('keeps $method submission and labels correct (approval: $approval)', async ({ method, approval, confirmation }) => {
		setActivePinia(createPinia())
		const signStore = useSignStore()
		const signMethodsStore = useSignMethodsStore()
		const uuid = approval ? 'approval-file-uuid' : 'regular-sign-request-uuid'
		const methods = method === 'password'
			? { password: { name: 'password', label: 'Signature password', hasSignatureFile: true } }
			: { clickToSign: { name: 'clickToSign', label: 'Click to Sign', enabled: true } }

		signStore.document = {
			id: 42,
			name: 'Review document.pdf',
			description: '',
			status: FILE_STATUS.ABLE_TO_SIGN,
			statusText: 'Ready to sign',
			url: '/apps/libresign/p/pdf/test-file',
			nodeId: 42,
			nodeType: 'file',
			uuid: approval ? uuid : 'regular-file-uuid',
			signers: approval
				? []
				: [{
					me: true,
					signRequestId: 501,
					status: SIGN_REQUEST_STATUS.ABLE_TO_SIGN,
					sign_request_uuid: uuid,
				}],
			visibleElements: [],
			settings: {
				canSign: true,
				isApprover: approval,
				signatureMethods: methods,
			},
		}
		signMethodsStore.settings = methods
		const submitSignature = vi.fn().mockResolvedValue({ status: 'signed', data: {} })
		signStore.submitSignature = submitSignature as typeof signStore.submitSignature
		signStore.queueAction('sign')

		currentRoute.name = approval ? 'IdDocsApprove' : 'SignPDF'
		currentRoute.params.uuid = uuid
		currentRoute.query = approval ? { idDocApproval: 'true' } : {}
		currentRoute.path = approval ? `/f/id-docs/approve/${uuid}` : `/f/sign/${uuid}/pdf`

		wrapper = mount(Sign, {
			global: {
				stubs: {
					Draw: true,
					Documents: true,
					Signatures: true,
					ManagePassword: true,
					CreatePassword: true,
					UploadCertificate: true,
				},
			},
		})
		await flushPromises()

		expect(wrapper.text().includes('Review the identification document uploaded by the signer')).toBe(approval)
		expect(signMethodsStore.modal[method]).toBe(true)
		const element = document.body.querySelector<HTMLElement>('[role="dialog"]')
		expect(element).not.toBeNull()
		const dialog = new DOMWrapper(element as HTMLElement)
		expect(dialog.find('.dialog__name').text()).toBe(approval ? 'Document approval' : 'Sign document')
		expect(dialog.text()).toContain(confirmation)

		if (method === 'password') {
			await dialog.find('input').setValue('test-password')
		}
		const action = dialog.find('.dialog__actions .button-vue--primary')
		expect(action.text().trim()).toBe(approval ? 'Sign and approve document' : 'Sign document')
		expect(action.attributes('disabled')).toBeUndefined()
		await action.trigger('click')
		await flushPromises()

		expect(submitSignature).toHaveBeenCalledWith(
			expect.objectContaining({
				method,
				...(method === 'password' ? { token: 'test-password' } : {}),
			}),
			uuid,
			{ documentId: 42 },
		)
	})
})
