/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import { getCurrentUser } from '@nextcloud/auth'

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
const multipleIntegrations = [...integrations, { id: 'second', name: 'Second provider', driverId: 'csc-v1' }]
const baseSession = { id: 'session-1', status: 'preparing', revision: 1, integrationId: 'lab', credentialId: 'synthetic-cert', certificateSha256: 'a'.repeat(64), expiresAt: '2099-01-01' }

function mountSigner(extraProps: Record<string, unknown> = {}) {
	return mount(ExternalSigning, {
		props: { signRequestUuid: 'request-one', emailMethod: 'email', boundEmailHash: 'bound-hash', boundIdentities: [{ method: 'email', value: '' }], ...extraProps },
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
	await clickButton(wrapper, 'Email me a code')
	await wrapper.find('input[type="text"]').setValue('123456')
	await clickButton(wrapper, 'Continue')
}

describe('external signing within the existing signer UI', () => {
	it('shows a signed-in account choice only when the signer request explicitly binds that account', () => {
		vi.mocked(getCurrentUser).mockReturnValueOnce({ uid: 'signed-in-user' } as ReturnType<typeof getCurrentUser>)
		const wrapper = mountSigner({ boundIdentities: [
			{ method: 'account', value: 'signed-in-user' },
			{ method: 'email', value: 'guest@example.org' },
		] })
		expect(wrapper.text()).toContain('Use my signed-in account')
		expect(wrapper.text()).toContain('Use a code sent to my email')
		expect(wrapper.find('#external-signing-method').exists()).toBe(false)
		expect(wrapper.findAll('input[type="radio"][name="external-signing-identity"]')).toHaveLength(2)
		wrapper.unmount()
	})

	it('does not invent fallback verification methods for an explicitly empty signer policy', () => {
		const wrapper = mountSigner({ boundIdentities: [] })
		expect(wrapper.findAll('input[name="external-signing-identity"]')).toHaveLength(0)
		expect(wrapper.text()).toContain('No supported identity check is available')
		expect(wrapper.findAll('button').some(button => button.text().includes('Email me a code'))).toBe(false)
		wrapper.unmount()
	})

	it('never offers an account merely because a user is logged in when signer methods were omitted', () => {
		vi.mocked(getCurrentUser).mockReturnValueOnce({ uid: 'signed-in-user' } as ReturnType<typeof getCurrentUser>)
		const wrapper = mountSigner({ boundIdentities: undefined })
		expect(wrapper.text()).not.toContain('Use my signed-in account')
		expect(wrapper.text()).not.toContain('Use a code sent to my email')
		expect(wrapper.text()).toContain('No supported identity check is available')
		wrapper.unmount()
	})

	it('shows only the email identity when the signed-in account is not a bound identity', async () => {
		vi.mocked(getCurrentUser).mockReturnValueOnce({ uid: 'signed-in-user' } as ReturnType<typeof getCurrentUser>)
		const wrapper = mountSigner({ boundIdentities: [{ method: 'email', value: 'guest@example.org', requirement: 'required' }] })
		expect(wrapper.findAll('input[name="external-signing-identity"]')).toHaveLength(0)
		expect(wrapper.text()).toContain('Use a code sent to my email')
		expect(wrapper.text()).not.toContain('Use my signed-in account')
		wrapper.unmount()
	})

	it('reuses an already verified LibreSign email code without a second request', async () => {
		const wrapper = mountSigner({
			boundIdentities: [{ method: 'email', value: 'guest@example.org' }],
			existingEmailCode: '123456',
		})
		await flushPromises()
		expect(wrapper.find('input[type="email"]').exists()).toBe(false)
		expect(wrapper.text()).toContain('Code sent to guest@example.org')
		expect((wrapper.find('input[autocomplete="one-time-code"]').element as HTMLInputElement).value).toBe('123456')
		expect(wrapper.findAll('button').some(button => button.text().includes('Email me a code'))).toBe(false)
		await clickButton(wrapper, 'Continue')
		expect(axios.post).not.toHaveBeenCalled()
		expect(discoverExternalSigning).toHaveBeenCalledWith({
			uuid: 'request-one', method: 'email', identifyValue: 'guest@example.org', token: '123456',
		})
		wrapper.unmount()
	})

	it('reuses a previously requested email code after switching from a bound account method', async () => {
		vi.mocked(getCurrentUser).mockReturnValueOnce({ uid: 'signed-in-user' } as ReturnType<typeof getCurrentUser>)
		const wrapper = mountSigner({
			boundIdentities: [
				{ method: 'account', value: 'signed-in-user' },
				{ method: 'email', value: 'guest@example.org' },
			],
			existingEmailCode: '123456',
		})
		expect(wrapper.find('input[autocomplete="one-time-code"]').exists()).toBe(false)
		await wrapper.find('input[type="radio"][value="email"]').setValue()
		await flushPromises()
		expect((wrapper.find('input[autocomplete="one-time-code"]').element as HTMLInputElement).value).toBe('123456')
		expect(wrapper.findAll('button').some(button => button.text().includes('Email me a code'))).toBe(false)
		wrapper.unmount()
	})

	it('does not show an email option for an account-only signer', async () => {
		vi.mocked(getCurrentUser).mockReturnValueOnce({ uid: 'signed-in-user' } as ReturnType<typeof getCurrentUser>)
		const wrapper = mountSigner({ boundIdentities: [{ method: 'account', value: 'signed-in-user' }] })
		expect(wrapper.findAll('input[name="external-signing-identity"]')).toHaveLength(0)
		expect(wrapper.text()).toContain('Use my signed-in account')
		expect(wrapper.find('input[type="email"]').exists()).toBe(false)
		wrapper.unmount()
	})

	it('uses the existing code-request endpoint for a WhatsApp-bound signer', async () => {
		const wrapper = mountSigner({ boundIdentities: [{ method: 'whatsapp', value: '+5521999999999', requirement: 'required' }] })
		expect(wrapper.findAll('input[name="external-signing-identity"]')).toHaveLength(0)
		expect(wrapper.text()).toContain('Use a code sent by WhatsApp')
		await clickButton(wrapper, 'Send me a verification code')
		expect(axios.post).toHaveBeenCalledWith('/code/request-one', { identifyMethod: 'whatsapp', signMethod: 'whatsappToken' })
		expect(wrapper.find('input[autocomplete="one-time-code"]').exists()).toBe(true)
		await wrapper.find('input[autocomplete="one-time-code"]').setValue('946275')
		await clickButton(wrapper, 'Continue')
		expect(discoverExternalSigning).toHaveBeenCalledWith({
			uuid: 'request-one', method: 'whatsapp', identifyValue: '+5521999999999', token: '946275',
		})
		wrapper.unmount()
	})

	it('explains the signing journey in everyday language and keeps technical formats optional', async () => {
		const wrapper = mountSigner()
		expect(wrapper.find('.external-signing__intro').text()).toContain('Confirm your identity')
		expect(wrapper.find('.external-signing__step').text()).toContain('Confirm your identity')
		expect(wrapper.find('.external-signing__intro').text()).not.toMatch(/CSC|PAdES|SAD|OAuth/)
		await wrapper.find('input[type="email"]').setValue('guest@example.org')
		expect(wrapper.findAll('button').some(button => button.text().includes('Email me a code'))).toBe(true)
		wrapper.unmount()
	})

	it('explains standard PDF compatibility inside optional advanced options', async () => {
		vi.mocked(discoverExternalSigning).mockImplementation(async (_identity, integrationId) => integrationId
			? { integrations, integration: integrations[0], credentials, syntheticOnly: true, personalSignerAuthorization: false, session: null }
			: { integrations, integration: null, credentials: [], syntheticOnly: true, personalSignerAuthorization: false, session: null })
		const wrapper = mountSigner()
		await authorize(wrapper)
		expect(wrapper.findAll('.external-signing__technical-details').some(item => item.find('summary').text() === 'Advanced options')).toBe(true)
		expect(wrapper.find('#external-signing-profile').text()).toContain('Recommended')
		expect(wrapper.find('#external-signing-profile').text()).toContain('Basic PDF')
		expect(wrapper.find('#external-signing-profile').text()).not.toContain('PAdES')
		expect(wrapper.findAll('.external-signing__technical-details').some(item => item.text().includes('Technical format names'))).toBe(true)
		expect((wrapper.find('#external-signing-profile').element as HTMLSelectElement).value).toBe('pades-baseline-b')
		wrapper.unmount()
	})

	it('uses a neutral signing title without a development-status label', () => {
		const wrapper = mountSigner()
		expect(wrapper.text()).toContain('Sign with a digital certificate')
		expect(wrapper.text()).not.toMatch(/experimental/i)
		wrapper.unmount()
	})
	beforeEach(() => {
		vi.clearAllMocks()
		vi.mocked(axios.post).mockResolvedValue({ data: {} })
		vi.mocked(discoverExternalSigning).mockResolvedValue({
			integrations, integration: null, credentials: [], syntheticOnly: true, personalSignerAuthorization: false, session: null,
		})
	})
	afterEach(() => {
		vi.unstubAllGlobals()
	})

	it('describes pending authorization separately from an uncertain signature', async () => {
		vi.mocked(discoverExternalSigning).mockResolvedValue({
			integrations, integration: null, credentials: [], syntheticOnly: true,
			personalSignerAuthorization: false, session: { ...baseSession, status: 'authorization_pending' },
		})
		const wrapper = mountSigner()
		await authorize(wrapper)
		expect(wrapper.text()).toContain('If your signing service asks for approval')
		expect(wrapper.text()).not.toContain('Contact your administrator to reconcile')
		wrapper.unmount()
	})

	it('reports failed provider discovery without implying that a signature was submitted', async () => {
		vi.mocked(discoverExternalSigning).mockRejectedValue(new Error('offline'))
		const wrapper = mountSigner()
		await wrapper.find('input[type="email"]').setValue('guest@example.org')
		await clickButton(wrapper, 'Email me a code')
		await wrapper.find('input[type="text"]').setValue('123456')
		await clickButton(wrapper, 'Continue')
		expect(wrapper.text()).toContain('Could not load signing services')
		expect(wrapper.text()).not.toContain('could not confirm the signing result')
		wrapper.unmount()
	})

	it('shows only the send action before requesting a code and allows editing and resending after', async () => {
		const wrapper = mountSigner()
		expect(wrapper.find('input[autocomplete="one-time-code"]').exists()).toBe(false)
		expect(wrapper.findAll('button').some(button => button.text().includes('Continue'))).toBe(false)
		expect(wrapper.findAll('button').some(button => button.text().includes('Send another code'))).toBe(false)
		await wrapper.find('input[type="email"]').setValue('guest@example.org')
		await clickButton(wrapper, 'Email me a code')
		expect(wrapper.find('input[autocomplete="one-time-code"]').exists()).toBe(true)
		expect(wrapper.text()).toContain('guest@example.org')
		expect(wrapper.find('input[type="email"]').exists()).toBe(false)
		await clickButton(wrapper, 'Change email')
		expect(wrapper.find('input[type="email"]').exists()).toBe(true)
		expect(wrapper.find('input[autocomplete="one-time-code"]').exists()).toBe(false)
		expect(wrapper.findAll('button').some(button => button.text().includes('Continue'))).toBe(false)
		await clickButton(wrapper, 'Email me a code')
		await wrapper.find('input[autocomplete="one-time-code"]').setValue('123456')
		await clickButton(wrapper, 'Send another code')
		expect((wrapper.find('input[autocomplete="one-time-code"]').element as HTMLInputElement).value).toBe('')
		expect(axios.post).toHaveBeenCalledTimes(3)
		wrapper.unmount()
	})

	it('keeps a recipient-bound email fixed and points corrections to the sender', async () => {
		const wrapper = mountSigner({ boundIdentities: [{ method: 'email', value: 'guest@example.org' }] })
		expect(wrapper.text()).toContain('guest@example.org')
		expect(wrapper.find('input[type="email"]').exists()).toBe(false)
		await clickButton(wrapper, 'Email me a code')
		expect(wrapper.findAll('button').some(button => button.text().includes('Change email'))).toBe(false)
		expect(wrapper.findAll('button').some(button => button.text().includes('Send another code'))).toBe(true)
		wrapper.unmount()
	})

	it('shows code entry only after requesting the verification email', async () => {
		const wrapper = mountSigner()
		expect(wrapper.find('input[type="text"]').exists()).toBe(false)
		await wrapper.find('input[type="email"]').setValue('guest@example.org')
		await clickButton(wrapper, 'Email me a code')
		expect(wrapper.find('input[type="text"]').exists()).toBe(true)
		expect(wrapper.text()).toContain('Code sent to guest@example.org')
		wrapper.unmount()
	})

	it('explains when an eligible provider has no certificates without offering signing', async () => {
		vi.mocked(discoverExternalSigning).mockImplementation(async (_identity, integrationId) => ({
			integrations,
			integration: integrationId ? integrations[0] : null,
			credentials: [], syntheticOnly: true, personalSignerAuthorization: false, session: null,
		}))
		const wrapper = mountSigner()
		await authorize(wrapper)
		expect(wrapper.text()).toContain('This signing service has no certificate you can use')
		expect(wrapper.findAll('button').some(button => button.text().includes('Continue to sign'))).toBe(false)
		wrapper.unmount()
	})

	it('does not suggest choosing another service when the only permitted one has no certificate', async () => {
		vi.mocked(discoverExternalSigning).mockImplementation(async (_identity, integrationId) => ({
			integrations, integration: integrationId ? integrations[0] : null, credentials: [],
			syntheticOnly: true, personalSignerAuthorization: false, session: null,
		}))
		const wrapper = mountSigner()
		await authorize(wrapper)
		expect(wrapper.text()).toContain('This signing service has no certificate you can use')
		expect(wrapper.text()).not.toContain('Try another signing service')
		wrapper.unmount()
	})

	it('announces provider lookup progress without claiming an uncertain signing result', async () => {
		let resolveDiscovery!: (value: Awaited<ReturnType<typeof discoverExternalSigning>>) => void
		vi.mocked(discoverExternalSigning).mockImplementationOnce(() => new Promise(resolve => {
			resolveDiscovery = resolve
		}))
		const wrapper = mountSigner()
		await wrapper.find('input[type="email"]').setValue('guest@example.org')
		await clickButton(wrapper, 'Email me a code')
		await wrapper.find('input[type="text"]').setValue('123456')
		const button = wrapper.findAll('button').find(item => item.text().includes('Continue'))
		await button!.trigger('click')
		expect(wrapper.text()).toContain('Finding signing services')
		resolveDiscovery({ integrations: multipleIntegrations, integration: null, credentials: [], syntheticOnly: true, personalSignerAuthorization: false, session: null })
		await flushPromises()
		expect(wrapper.text()).not.toContain('Finding signing services')
		wrapper.unmount()
	})

	it('does not discover or expose providers before a guest supplies verified identity', async () => {
		const wrapper = mountSigner()
		expect(wrapper.text()).not.toContain('Synthetic provider')
		expect(wrapper.findAll('button').some(button => button.text().includes('Continue'))).toBe(false)
		expect(discoverExternalSigning).not.toHaveBeenCalled()
		await wrapper.find('input[type="email"]').setValue('other@example.org')
		await clickButton(wrapper, 'Email me a code')
		expect(axios.post).not.toHaveBeenCalled()
		wrapper.unmount()
	})

	it('keeps technical certificate details collapsed and selects PAdES B-B by default', async () => {
		vi.mocked(discoverExternalSigning).mockImplementation(async (_identity, integrationId) => integrationId
			? { integrations, integration: integrations[0], credentials, syntheticOnly: true, personalSignerAuthorization: false, session: null }
			: { integrations, integration: null, credentials: [], syntheticOnly: true, personalSignerAuthorization: false, session: null })
		const wrapper = mountSigner()
		await authorize(wrapper)
		await flushPromises()
		expect(wrapper.text()).toContain('This service uses sample certificates')
		expect(wrapper.find('details').attributes('open')).toBeUndefined()
		expect(wrapper.find('.external-signing__technical-details').exists()).toBe(true)
		expect(wrapper.find('#external-signing-profile').element).toBeInstanceOf(HTMLSelectElement)
		expect((wrapper.find('#external-signing-profile').element as HTMLSelectElement).value).toBe('pades-baseline-b')
		wrapper.unmount()
	})

	it('does not display a synthetic-certificate warning before a certificate is selected', () => {
		const wrapper = mountSigner()
		expect(wrapper.text()).not.toContain('This service uses sample certificates')
		wrapper.unmount()
	})

	it('does not show the sample-certificate warning for a real provider response', async () => {
		vi.mocked(discoverExternalSigning).mockImplementation(async (_identity, integrationId) => ({
			integrations,
			integration: integrationId ? integrations[0] : null,
			credentials: integrationId ? credentials : [], syntheticOnly: false,
			personalSignerAuthorization: true, session: null,
		}))
		const wrapper = mountSigner()
		await authorize(wrapper)
		expect(wrapper.text()).not.toContain('sample certificates')
		wrapper.unmount()
	})

	it('requests an existing LibreSign signer code, then lists authorized integrations', async () => {
		vi.mocked(discoverExternalSigning).mockResolvedValue({
			integrations: multipleIntegrations, integration: null, credentials: [], syntheticOnly: true, personalSignerAuthorization: false, session: null,
		})
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

	it('automatically offers the only authorized provider and its one eligible certificate', async () => {
		vi.mocked(discoverExternalSigning).mockImplementation(async (_identity, providerId) => providerId
			? { integrations, integration: integrations[0], credentials, syntheticOnly: true, personalSignerAuthorization: false, session: null }
			: { integrations, integration: null, credentials: [], syntheticOnly: true, personalSignerAuthorization: false, session: null })
		const wrapper = mountSigner()
		await authorize(wrapper)
		expect(discoverExternalSigning).toHaveBeenCalledTimes(2)
		expect(vi.mocked(discoverExternalSigning).mock.calls[1][1]).toBe('lab')
		expect(wrapper.find('#external-signing-integration').exists()).toBe(false)
		expect(wrapper.find('#external-signing-credential').exists()).toBe(false)
		expect(wrapper.text()).toContain('Step 2 of 3 — Review your certificate')
		expect(wrapper.text()).toContain('Signing service: Synthetic provider')
		expect(wrapper.text()).toContain('Your certificate: Laboratory certificate')
		expect((wrapper.find('#external-signing-profile').element as HTMLSelectElement).value).toBe('pades-baseline-b')
		wrapper.unmount()
	})

	it('shows two permitted services as accessible, individually selectable options', async () => {
		vi.mocked(discoverExternalSigning).mockImplementation(async (_identity, integrationId) => ({
			integrations: multipleIntegrations,
			integration: integrationId ? multipleIntegrations.find(provider => provider.id === integrationId) ?? null : null,
			credentials: integrationId ? credentials : [], syntheticOnly: true,
			personalSignerAuthorization: false, session: null,
		}))
		const wrapper = mountSigner()
		await authorize(wrapper)
		expect(wrapper.find('#external-signing-integration').exists()).toBe(false)
		expect(wrapper.findAll('input[type="radio"][name="external-signing-provider"]')).toHaveLength(2)
		expect(wrapper.findAll('input[type="radio"][name="external-signing-provider"]').every(radio => !(radio.element as HTMLInputElement).checked)).toBe(true)
		expect(wrapper.text()).toContain('Step 2 of 3 — Choose how to sign')
		expect(wrapper.text()).toContain('Choose a signing service')
		expect(wrapper.text()).not.toContain('Laboratory certificate')
		await wrapper.find('input[type="radio"][value="second"]').setValue()
		await flushPromises()
		expect(discoverExternalSigning).toHaveBeenLastCalledWith(expect.objectContaining({ uuid: 'request-one' }), 'second')
		expect(wrapper.text()).toContain('Laboratory certificate')
		wrapper.unmount()
	})

	it('recovers after a temporary certificate lookup failure without forcing a new identity check', async () => {
		vi.mocked(discoverExternalSigning)
			.mockResolvedValueOnce({ integrations: multipleIntegrations, integration: null, credentials: [], syntheticOnly: true, personalSignerAuthorization: false, session: null })
			.mockRejectedValueOnce(new Error('temporary service outage'))
			.mockResolvedValueOnce({ integrations: multipleIntegrations, integration: multipleIntegrations[0], credentials, syntheticOnly: true, personalSignerAuthorization: false, session: null })
		const wrapper = mountSigner()
		await authorize(wrapper)
		await wrapper.find('input[type="radio"][value="lab"]').setValue()
		await flushPromises()
		expect(wrapper.text()).toContain('Could not load signing services')
		expect(wrapper.text()).toContain('Try loading certificates again')
		expect(wrapper.text()).not.toContain('Laboratory certificate')
		await clickButton(wrapper, 'Try loading certificates again')
		expect(wrapper.text()).toContain('Laboratory certificate')
		expect(wrapper.text()).not.toContain('Could not load signing services')
		expect(discoverExternalSigning).toHaveBeenCalledTimes(3)
		wrapper.unmount()
	})

	it('uses a compact select when more than five signing services are permitted', async () => {
		const manyProviders = Array.from({ length: 6 }, (_, index) => ({ id: `provider-${index}`, name: `Signing service ${index + 1}`, driverId: 'csc-v1' }))
		vi.mocked(discoverExternalSigning).mockImplementation(async (_identity, integrationId) => ({
			integrations: manyProviders,
			integration: integrationId ? manyProviders.find(provider => provider.id === integrationId) ?? null : null,
			credentials: integrationId ? credentials : [], syntheticOnly: true, personalSignerAuthorization: false, session: null,
		}))
		const wrapper = mountSigner()
		await authorize(wrapper)
		expect(wrapper.findAll('input[type="radio"][name="external-signing-provider"]')).toHaveLength(0)
		expect((wrapper.find('#external-signing-integration').element as HTMLSelectElement).value).toBe('')
		await wrapper.find('#external-signing-integration').setValue('provider-4')
		await flushPromises()
		expect(discoverExternalSigning).toHaveBeenLastCalledWith(expect.objectContaining({ uuid: 'request-one' }), 'provider-4')
		wrapper.unmount()
	})

	it('shows several certificates with expiry dates and requires an explicit certificate choice', async () => {
		const severalCertificates = [credentials[0], { ...credentials[0], id: 'another-cert', subject: 'Second test certificate', validTo: '2030-10-03' }]
		vi.mocked(discoverExternalSigning).mockImplementation(async (_identity, providerId) => ({
			integrations,
			integration: providerId ? integrations[0] : null,
			credentials: providerId ? severalCertificates : [], syntheticOnly: true, personalSignerAuthorization: false, session: null,
		}))
		const wrapper = mountSigner()
		await authorize(wrapper)
		expect(wrapper.find('#external-signing-credential').exists()).toBe(false)
		expect(wrapper.findAll('input[type="radio"][name="external-signing-certificate"]')).toHaveLength(2)
		expect(wrapper.text()).toContain('Second test certificate')
		expect(wrapper.text()).toContain('2030')
		expect(wrapper.findAll('button').some(button => button.text().includes('Continue to sign'))).toBe(false)
		await wrapper.find('input[type="radio"][value="another-cert"]').setValue()
		await flushPromises()
		expect(wrapper.findAll('button').some(button => button.text().includes('Continue to sign'))).toBe(true)
		expect(wrapper.find('.external-signing__fingerprint').exists()).toBe(true)
		wrapper.unmount()
	})

	it('uses a compact select when more than five certificates are available', async () => {
		const manyCertificates = Array.from({ length: 6 }, (_, i) => ({ ...credentials[0], id: `cert-${i}`, subject: `Test certificate ${i + 1}` }))
		vi.mocked(discoverExternalSigning).mockImplementation(async (_identity, providerId) => ({
			integrations, integration: providerId ? integrations[0] : null,
			credentials: providerId ? manyCertificates : [], syntheticOnly: true, personalSignerAuthorization: false, session: null,
		}))
		const wrapper = mountSigner()
		await authorize(wrapper)
		expect(wrapper.findAll('input[type="radio"][name="external-signing-certificate"]')).toHaveLength(0)
		expect((wrapper.find('#external-signing-credential').element as HTMLSelectElement).value).toBe('')
		await wrapper.find('#external-signing-credential').setValue('cert-2')
		expect(wrapper.text()).toContain('Test certificate 3')
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
		await flushPromises()
		expect(wrapper.text()).toContain('Laboratory certificate')
		await clickButton(wrapper, 'Continue to sign')
		expect(startExternalSigning).toHaveBeenCalledExactlyOnceWith(
			{ uuid: 'request-one', method: 'email', identifyValue: 'guest@example.org', token: '123456' },
			'lab',
'synthetic-cert',
'stable-idempotency-attempt',
'pades-baseline-b',
		)
		await clickButton(wrapper, 'Sign this document')
		expect(wrapper.text()).toContain('We could not confirm the result')
		expect(wrapper.text()).not.toContain('Sign this document')
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
		await flushPromises()
		await clickButton(wrapper, 'Continue to sign')
		expect(startExternalSigning).toHaveBeenCalledTimes(1)
		expect(wrapper.text()).toContain('We could not confirm whether the document is ready.')
		const prepare = wrapper.findAll('button').find(button => button.text().includes('Continue to sign'))
		expect(prepare?.attributes('disabled')).toBeDefined()
		await clickButton(wrapper, 'Check status')
		expect(statusExternalSigning).toHaveBeenCalledTimes(1)
		await clickButton(wrapper, 'Continue to sign')
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
		expect(wrapper.text()).not.toContain('We could not confirm the result')
		await authorize(wrapper)
		expect(wrapper.text()).toContain('We could not confirm the result')
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
		await clickButton(wrapper, 'Sign this document')
		expect(signedCallback).toHaveBeenCalledWith({ signRequestUuid: 'request-one' })
		wrapper.unmount()
	})

	it('can authorize a new document while the previous provider lookup is unresolved', async () => {
		let finishOld!: (data: Awaited<ReturnType<typeof discoverExternalSigning>>) => void
		vi.mocked(discoverExternalSigning)
			.mockImplementationOnce(() => new Promise(resolve => { finishOld = resolve }))
			.mockResolvedValueOnce({ integrations: multipleIntegrations, integration: null, credentials: [], syntheticOnly: true, personalSignerAuthorization: false, session: null })
		const wrapper = mountSigner()
		await wrapper.find('input[type="email"]').setValue('guest@example.org')
		await clickButton(wrapper, 'Email me a code')
		await wrapper.find('input[type="text"]').setValue('123456')
		const button = wrapper.findAll('button').find(item => item.text().includes('Continue'))
		await button!.trigger('click')
		await wrapper.setProps({ signRequestUuid: 'request-two' })
		await wrapper.find('input[type="email"]').setValue('guest@example.org')
		await clickButton(wrapper, 'Email me a code')
		await wrapper.find('input[type="text"]').setValue('654321')
		await clickButton(wrapper, 'Continue')
		expect(discoverExternalSigning).toHaveBeenCalledTimes(2)
		expect(vi.mocked(discoverExternalSigning).mock.calls[1][0]).toMatchObject({ uuid: 'request-two', token: '654321' })
		finishOld({ integrations: [], integration: null, credentials: [], syntheticOnly: true, personalSignerAuthorization: false, session: null })
		await flushPromises()
		expect(wrapper.text()).toContain('Synthetic provider')
		wrapper.unmount()
	})

	it('ignores a stale provider list after switching SignRequest', async () => {
		let resolveRequest!: (data: Awaited<ReturnType<typeof discoverExternalSigning>>) => void
		vi.mocked(discoverExternalSigning).mockImplementation(() => new Promise(resolve => {
			resolveRequest = resolve
		}))
		const wrapper = mountSigner()
		await wrapper.find('input[type="email"]').setValue('guest@example.org')
		await clickButton(wrapper, 'Email me a code')
		await wrapper.find('input[type="text"]').setValue('123456')
		const call = wrapper.findAll('button').find(item => item.text().includes('Continue'))
		await call!.trigger('click')
		await wrapper.setProps({ signRequestUuid: 'request-two' })
		resolveRequest({ integrations, integration: null, credentials: [], syntheticOnly: true, personalSignerAuthorization: false, session: null })
		await flushPromises()
		expect(wrapper.text()).not.toContain('Synthetic provider')
		wrapper.unmount()
	})

	it.each([
		{ change: 'bound email hash', props: { boundEmailHash: 'another-signer-hash' } },
		{ change: 'identification method', props: { emailMethod: 'account' } },
	])('invalidates cached signing authorization when the $change changes', async ({ props }) => {
		vi.mocked(discoverExternalSigning).mockResolvedValue({
			integrations, integration: null, credentials: [], syntheticOnly: true,
			personalSignerAuthorization: false, session: null,
		})
		const wrapper = mountSigner()
		await authorize(wrapper)
		expect(wrapper.text()).toContain('Synthetic provider')
		await wrapper.setProps(props)
		expect(wrapper.text()).not.toContain('Synthetic provider')
		expect(wrapper.findAll('button').some(button => button.text().includes('Continue'))).toBe(false)
		expect(wrapper.findAll('button').some(button => button.text().includes('Email me a code'))).toBe(true)
		wrapper.unmount()
	})

	it('ignores an obsolete signing status refresh after switching documents', async () => {
		vi.mocked(discoverExternalSigning).mockResolvedValue({
			integrations, integration: null, credentials: [], syntheticOnly: true,
			personalSignerAuthorization: false, session: baseSession,
		})
		let finishOld!: (session: typeof baseSession) => void
		vi.mocked(statusExternalSigning).mockImplementationOnce(() => new Promise(resolve => {
			finishOld = resolve
		}))
		const wrapper = mountSigner()
		await authorize(wrapper)
		expect(wrapper.text()).toContain('Preparing your document')
		const refresh = wrapper.findAll('button').find(button => button.text().includes('Check status'))
		expect(refresh).toBeDefined()
		await refresh!.trigger('click')
		expect(statusExternalSigning).toHaveBeenCalledExactlyOnceWith({
			uuid: 'request-one', method: 'email', identifyValue: 'guest@example.org', token: '123456',
		})
		await wrapper.setProps({ signRequestUuid: 'request-two' })
		finishOld({ ...baseSession, status: 'completed' })
		await flushPromises()
		expect(wrapper.text()).not.toContain('Document signed successfully')
		expect(wrapper.text()).not.toContain('Preparing your document')
		expect(wrapper.emitted('signed')).toBeUndefined()
		wrapper.unmount()
	})
})
