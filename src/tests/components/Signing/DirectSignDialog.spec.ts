/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useUserConfigStore } from '../../../store/userconfig.js'
import { nextTick } from 'vue'
import { showSuccess } from '@nextcloud/dialogs'

import DirectSignDialog from '../../../components/Signing/DirectSignDialog.vue'
import { downloadSignedPdf, signPdfDirectly } from '../../../services/directSigning.ts'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())
vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app, key, fallback) => key === 'config' ? { skip_direct_sign_confirmation: false } : fallback),
}))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))
vi.mock('../../../services/directSigning.ts', () => ({
	signPdfDirectly: vi.fn(),
	downloadSignedPdf: vi.fn(),
}))

const sign = vi.mocked(signPdfDirectly)
const download = vi.mocked(downloadSignedPdf)

describe('immediate signature confirmation', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		setActivePinia(createPinia())
	})

	function dialog() {
		return mount(DirectSignDialog, {
			props: { name: 'document.pdf', nodeId: 42 },
			global: { stubs: {
					NcDialog: { name: 'NcDialog', props: ['buttons'], template: '<div><slot /></div>' },
					NcNoteCard: { name: 'NcNoteCard', template: '<div><slot /></div>' },
					NcCheckboxRadioSwitch: { name: 'NcCheckboxRadioSwitch', props: ['modelValue'], template: '<label><slot /></label>' },
				} },
		})
	}

	type Button = { label: string; disabled: boolean; callback: () => void | Promise<void> }
	function buttons(wrapper: ReturnType<typeof dialog>): Button[] {
		return wrapper.findComponent({ name: 'NcDialog' }).props('buttons') as Button[]
	}

	it('waits for explicit confirmation before calling the API', async () => {
		const wrapper = dialog()
		expect(sign).not.toHaveBeenCalled()
		expect(buttons(wrapper).find(button => button.label === 'Sign now')!.label).toBe('Sign now')
		const pdf = new Blob(['signed'], { type: 'application/pdf' })
		sign.mockResolvedValueOnce({ pdf, filename: 'document.pdf' })
		await buttons(wrapper).find(button => button.label === 'Sign now')!.callback()
		expect(sign).toHaveBeenCalledExactlyOnceWith(expect.objectContaining({ name: 'document.pdf', nodeId: 42 }))
		expect(download).toHaveBeenCalledExactlyOnceWith(pdf, 'document.pdf')
		expect(showSuccess).toHaveBeenCalledOnce()
		expect(wrapper.emitted('close')?.[0]).toEqual([true])
	})

	it('remembers dismissal only after successful signing', async () => {
		const config = useUserConfigStore()
		const update = vi.spyOn(config, 'update').mockResolvedValue(undefined)
		const wrapper = dialog()
		wrapper.findComponent({ name: 'NcCheckboxRadioSwitch' }).vm.$emit('update:modelValue', true)
		await nextTick()
		const pdf = new Blob(['signed'], { type: 'application/pdf' })
		sign.mockResolvedValueOnce({ pdf, filename: 'document.pdf' })
		await buttons(wrapper).find(button => button.label === 'Sign now')!.callback()
		expect(update).toHaveBeenCalledWith('skip_direct_sign_confirmation', true)
	})

	it('signs immediately without a confirmation if user has dismissed it', async () => {
		const config = useUserConfigStore()
		config.onUpdate('skip_direct_sign_confirmation', true)
		const pdf = new Blob(['signed'], { type: 'application/pdf' })
		sign.mockResolvedValueOnce({ pdf, filename: 'document.pdf' })
		const wrapper = dialog()
		await flushPromises()
		expect(wrapper.findComponent({ name: 'NcDialog' }).exists()).toBe(false)
		expect(sign).toHaveBeenCalledOnce()
		expect(download).toHaveBeenCalledOnce()
		expect(wrapper.emitted('close')?.[0]).toEqual([true])
	})

	it('shows the error dialog if silent signing encounters an uncertain result', async () => {
		useUserConfigStore().onUpdate('skip_direct_sign_confirmation', true)
		sign.mockRejectedValueOnce(new Error('network lost'))
		const wrapper = dialog()
		await flushPromises()
		expect(wrapper.findComponent({ name: 'NcDialog' }).exists()).toBe(true)
		expect(wrapper.text()).toContain('signing result is unknown')
		expect(buttons(wrapper).find(button => button.label === 'Sign now')!.disabled).toBe(true)
	})

	it('can prepare signing without invoking the direct endpoint or saving the skip preference', async () => {
		const config = useUserConfigStore()
		const update = vi.spyOn(config, 'update').mockResolvedValue(undefined)
		const wrapper = dialog()
		wrapper.findComponent({ name: 'NcCheckboxRadioSwitch' }).vm.$emit('update:modelValue', true)
		await nextTick()
		const prepare = buttons(wrapper).find(button => button.label === 'Prepare signing')!
		prepare.callback()
		expect(wrapper.emitted('close')?.[0]).toEqual(['prepare'])
		expect(sign).not.toHaveBeenCalled()
		expect(update).not.toHaveBeenCalled()
	})

	it('allows cancellation without creating a signing request', () => {
		const wrapper = dialog()
		buttons(wrapper)[0].callback()
		expect(wrapper.emitted('close')?.[0]).toEqual([false])
		expect(sign).not.toHaveBeenCalled()
	})

	it('explains server validation failures without claiming a completed signature', async () => {
		const wrapper = dialog()
		sign.mockRejectedValueOnce({ response: { status: 403 } })
		await buttons(wrapper).find(button => button.label === 'Sign now')!.callback()
		await nextTick()
		expect(wrapper.text()).toContain('Could not sign this PDF')
		expect(wrapper.emitted('close')).toBeUndefined()
		expect(download).not.toHaveBeenCalled()
	})

	it('offers preparation on a pre-request authorization failure', async () => {
		const wrapper = dialog()
		sign.mockRejectedValueOnce({ response: { status: 403 } })
		await buttons(wrapper).find(button => button.label === 'Sign now')!.callback()
		await nextTick()
		expect(buttons(wrapper).find(button => button.label === 'Prepare signing')).toBeDefined()
		expect(wrapper.text()).toContain('Choose Prepare signing')
	})

	it('does not offer preparation after an uncertain validation error', async () => {
		const wrapper = dialog()
		sign.mockRejectedValueOnce({ response: { status: 422 } })
		await buttons(wrapper).find(button => button.label === 'Sign now')!.callback()
		await nextTick()
		expect(buttons(wrapper).find(button => button.label === 'Prepare signing')).toBeUndefined()
		expect(buttons(wrapper).find(button => button.label === 'Sign now')!.disabled).toBe(true)
	})

	it('blocks retries after server errors because the request may have been persisted', async () => {
		const wrapper = dialog()
		sign.mockRejectedValueOnce({ response: { status: 500 } })
		await buttons(wrapper).find(button => button.label === 'Sign now')!.callback()
		await nextTick()
		expect(buttons(wrapper).find(button => button.label === 'Sign now')!.disabled).toBe(true)
	})

	it('blocks retries if the network outcome is unknown', async () => {
		const wrapper = dialog()
		sign.mockRejectedValueOnce(new Error('connection lost'))
		await buttons(wrapper).find(button => button.label === 'Sign now')!.callback()
		await nextTick()
		expect(buttons(wrapper).find(button => button.label === 'Sign now')!.disabled).toBe(true)
		expect(wrapper.text()).toContain('signing result is unknown')
		await buttons(wrapper).find(button => button.label === 'Sign now')!.callback()
		expect(sign).toHaveBeenCalledTimes(1)
	})
})
