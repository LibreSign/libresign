/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import axios from '@nextcloud/axios'

import ExternalSigning from '../../../../views/SignPDF/_partials/ExternalSigning.vue'
import {
	continueExternalSigning,
	discoverExternalSigning,
	startExternalSigning,
	statusExternalSigning,
} from '../../../../services/externalSigning.ts'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: vi.fn(() => null) }))
vi.mock('@nextcloud/axios', () => ({ default: { post: vi.fn() } }))
vi.mock('blueimp-md5', () => ({ default: (email: string) => email === 'guest@example.org' ? 'bound-hash' : 'other' }))
vi.mock('../../../../store/sign.js', () => ({ useSignStore: () => ({ buildRequestCodeUrl: (uuid: string) => `/code/${uuid}` }) }))
vi.mock('../../../../services/externalSigning.ts', () => ({
	discoverExternalSigning: vi.fn(),
	startExternalSigning: vi.fn(),
	continueExternalSigning: vi.fn(),
	statusExternalSigning: vi.fn(),
	mayContinueExternalSigning: (status: string) => ['preparing', 'ready_to_sign', 'signature_received', 'completing'].includes(status),
	externalSigningRequiresReconciliation: (status: string) => ['uncertain', 'signing_pending', 'authorization_pending'].includes(status),
}))

const stubs = {
	NcButton: { props: ['disabled', 'variant'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' },
	NcNoteCard: { template: '<div><slot /></div>' },
	NcTextField: {
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template: '<input :value="modelValue" :aria-label="label" @input="$emit(\'update:modelValue\', $event.target.value)">',
	},
}

const credentials = [{ id: 'synthetic-cert', subject: 'Laboratory certificate', validTo: '2099-01-01', signatureAlgorithm: 'rsa-pkcs1-v1_5', digestAlgorithm: 'sha256', certificateSha256: 'a'.repeat(64), profiles: ['pdf-basic', 'pades-baseline-b'] }]
const integrations = [{ id: 'lab', name: 'Synthetic provider', driverId: 'csc-v1' }]
const baseSession = { id: 'session-1', status: 'preparing', revision: 1, integrationId: 'lab', credentialId: 'synthetic-cert', certificateSha256: 'a'.repeat(64), expiresAt: '2099-01-01' }

function mountSigner() {
	return mount(ExternalSigning, {
		props: { signRequestUuid: 'request-one', emailMethod: 'email', boundEmailHash: 'bound-hash' },
		global: { stubs },
	})
}

async function clickButton(wrapper: ReturnType<typeof mountSigner>, label: string) {
	const button = wrapper.findAll('button').find(item => item.text().includes(label))
	expect(button, `Button not found: ${label}`).toBeDefined()
	await button!.trigger('click')
	await flushPromises()
}

async function authorize(wrapper: ReturnType<typeof mountSigner>) {
	await wrapper.find('input[type="email"]').setValue('guest@example.org')
	await clickButton(wrapper, 'Send verification code')
	await wrapper.find('input[type="text"]').setValue('123456')
	await clickButton(wrapper, 'Find available signing providers')
}

describe('external signing within the existing signer UI', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		vi.mocked(axios.post).mockResolvedValue({ data: {} })
		vi.mocked(discoverExternalSigning).mockResolvedValue({
			integrations, integration: null, credentials: [], syntheticOnly: true, personalSignerAuthorization: false, session: null,
		})
	})
	afterEach(() => { vi.unstubAllGlobals() })

	it('does not discover or expose providers before a guest supplies verified identity', async () => {
		const wrapper = mountSigner()
		expect(wrapper.text()).not.toContain('Synthetic provider')
		await clickButton(wrapper, 'Find available signing providers')
		expect(discoverExternalSigning).not.toHaveBeenCalled()
		await wrapper.find('input[type="email"]').setValue('other@example.org')
		await clickButton(wrapper, 'Send verification code')
		expect(axios.post).not.toHaveBeenCalled()
		wrapper.unmount()
	})

	it('requests an existing LibreSign signer code, then lists authorized integrations', async () => {
		const wrapper = mountSigner()
		await authorize(wrapper)
		expect(axios.post).toHaveBeenCalledExactlyOnceWith('/code/request-one', { identifyMethod: 'email', signMethod: 'emailToken' })
		expect(discoverExternalSigning).toHaveBeenCalledExactlyOnceWith({
			uuid: 'request-one', method: 'email', identifyValue: 'guest@example.org', token: '123456',
		})
		expect(wrapper.text()).toContain('Synthetic provider')
		expect(wrapper.text()).not.toContain('Laboratory certificate')
		wrapper.unmount()
	})

	it('selects only backend-provided credential and never retries an uncertain remote signature', async () => {
		vi.stubGlobal('crypto', { randomUUID: () => 'stable-idempotency-attempt' })
		vi.mocked(discoverExternalSigning).mockImplementation(async (_auth, selected) => selected
			? { integrations, integration: integrations[0], credentials, syntheticOnly: true, personalSignerAuthorization: false, session: null }
			: { integrations, integration: null, credentials: [], syntheticOnly: true, personalSignerAuthorization: false, session: null })
		vi.mocked(startExternalSigning).mockResolvedValue(baseSession)
		vi.mocked(continueExternalSigning).mockResolvedValue({ session: { ...baseSession, status: 'uncertain' }, retryAllowed: false, publication: null })
		const wrapper = mountSigner()
		await authorize(wrapper)
		await wrapper.find('#external-signing-integration').setValue('lab')
		await flushPromises()
		expect(wrapper.text()).toContain('Laboratory certificate')
		await wrapper.find('#external-signing-credential').setValue('synthetic-cert')
		await clickButton(wrapper, 'Prepare external signature')
		expect(startExternalSigning).toHaveBeenCalledExactlyOnceWith(
			{ uuid: 'request-one', method: 'email', identifyValue: 'guest@example.org', token: '123456' },
			'lab',
'synthetic-cert',
'stable-idempotency-attempt',
'pdf-basic',
		)
		await clickButton(wrapper, 'Continue external signing')
		expect(wrapper.text()).toContain('Signature result uncertain')
		expect(wrapper.text()).not.toContain('Continue external signing')
		expect(continueExternalSigning).toHaveBeenCalledTimes(1)
		wrapper.unmount()
	})

	it('requires a status read and reuses the attempt after a preparation timeout', async () => {
		vi.stubGlobal('crypto', { randomUUID: () => 'retry-safe-attempt' })
		vi.mocked(discoverExternalSigning).mockImplementation(async (_identity, integrationId) => integrationId
			? { integrations, integration: integrations[0], credentials, syntheticOnly: true, personalSignerAuthorization: false, session: null }
			: { integrations, integration: null, credentials: [], syntheticOnly: true, personalSignerAuthorization: false, session: null })
		vi.mocked(startExternalSigning)
			.mockRejectedValueOnce(new Error('lost HTTP response'))
			.mockResolvedValueOnce(baseSession)
		vi.mocked(statusExternalSigning).mockResolvedValueOnce(null)
		const wrapper = mountSigner()
		await authorize(wrapper)
		await wrapper.find('#external-signing-integration').setValue('lab')
		await flushPromises()
		await wrapper.find('#external-signing-credential').setValue('synthetic-cert')
		await clickButton(wrapper, 'Prepare external signature')
		expect(startExternalSigning).toHaveBeenCalledTimes(1)
		expect(wrapper.text()).toContain('Check the signing session before another preparation attempt.')
		const prepare = wrapper.findAll('button').find(button => button.text().includes('Prepare external signature'))
		expect(prepare?.attributes('disabled')).toBeDefined()
		await clickButton(wrapper, 'Check signing status')
		expect(statusExternalSigning).toHaveBeenCalledTimes(1)
		await clickButton(wrapper, 'Prepare external signature')
		expect(startExternalSigning).toHaveBeenCalledTimes(2)
		expect(vi.mocked(startExternalSigning).mock.calls[0][3]).toBe('retry-safe-attempt')
		expect(vi.mocked(startExternalSigning).mock.calls[1][3]).toBe('retry-safe-attempt')
		wrapper.unmount()
	})

	it('restores a pending session only after the signer reauthorizes', async () => {
		vi.mocked(discoverExternalSigning).mockResolvedValue({
			integrations, integration: null, credentials: [], syntheticOnly: true,
			personalSignerAuthorization: false, session: { ...baseSession, status: 'uncertain' },
		})
		const wrapper = mountSigner()
		expect(wrapper.text()).not.toContain('Signature result uncertain')
		await authorize(wrapper)
		expect(wrapper.text()).toContain('Signature result uncertain')
		expect(startExternalSigning).not.toHaveBeenCalled()
		expect(continueExternalSigning).not.toHaveBeenCalled()
		wrapper.unmount()
	})

	it('emits signed only when the server confirms publication', async () => {
		vi.mocked(discoverExternalSigning).mockResolvedValue({
			integrations, integration: null, credentials: [], syntheticOnly: true,
			personalSignerAuthorization: false, session: baseSession,
		})
		vi.mocked(continueExternalSigning).mockResolvedValue({
			session: { ...baseSession, status: 'completed' }, retryAllowed: false,
			publication: { status: 'completed', sessionId: 'session-1', signedNodeId: 42, signedHash: 'a'.repeat(64) },
		})
		const wrapper = mountSigner()
		const signedCallback = vi.fn()
		await wrapper.setProps({ onSigned: signedCallback })
		await authorize(wrapper)
		await clickButton(wrapper, 'Continue external signing')
		expect(signedCallback).toHaveBeenCalledWith({ signRequestUuid: 'request-one' })
		wrapper.unmount()
	})

	it('can authorize a new document while the previous provider lookup is unresolved', async () => {
		let finishOld!: (data: Awaited<ReturnType<typeof discoverExternalSigning>>) => void
		vi.mocked(discoverExternalSigning)
			.mockImplementationOnce(() => new Promise(resolve => { finishOld = resolve }))
			.mockResolvedValueOnce({ integrations, integration: null, credentials: [], syntheticOnly: true, personalSignerAuthorization: false, session: null })
		const wrapper = mountSigner()
		await wrapper.find('input[type="email"]').setValue('guest@example.org')
		await clickButton(wrapper, 'Send verification code')
		await wrapper.find('input[type="text"]').setValue('123456')
		const button = wrapper.findAll('button').find(item => item.text().includes('Find available signing providers'))
		await button!.trigger('click')
		await wrapper.setProps({ signRequestUuid: 'request-two' })
		await wrapper.find('input[type="email"]').setValue('guest@example.org')
		await clickButton(wrapper, 'Send verification code')
		await wrapper.find('input[type="text"]').setValue('654321')
		await clickButton(wrapper, 'Find available signing providers')
		expect(discoverExternalSigning).toHaveBeenCalledTimes(2)
		expect(vi.mocked(discoverExternalSigning).mock.calls[1][0]).toMatchObject({ uuid: 'request-two', token: '654321' })
		finishOld({ integrations: [], integration: null, credentials: [], syntheticOnly: true, personalSignerAuthorization: false, session: null })
		await flushPromises()
		expect(wrapper.text()).toContain('Synthetic provider')
		wrapper.unmount()
	})

	it('ignores a stale provider list after switching SignRequest', async () => {
		let resolveRequest!: (data: Awaited<ReturnType<typeof discoverExternalSigning>>) => void
		vi.mocked(discoverExternalSigning).mockImplementation(() => new Promise(resolve => { resolveRequest = resolve }))
		const wrapper = mountSigner()
		await wrapper.find('input[type="email"]').setValue('guest@example.org')
		await clickButton(wrapper, 'Send verification code')
		await wrapper.find('input[type="text"]').setValue('123456')
		const call = wrapper.findAll('button').find(item => item.text().includes('Find available signing providers'))
		await call!.trigger('click')
		await wrapper.setProps({ signRequestUuid: 'request-two' })
		resolveRequest({ integrations, integration: null, credentials: [], syntheticOnly: true, personalSignerAuthorization: false, session: null })
		await flushPromises()
		expect(wrapper.text()).not.toContain('Synthetic provider')
		wrapper.unmount()
	})
})
