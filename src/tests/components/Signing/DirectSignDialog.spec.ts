/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { showSuccess } from '@nextcloud/dialogs'

import DirectSignDialog from '../../../components/Signing/DirectSignDialog.vue'
import { downloadSignedPdf, signPdfDirectly } from '../../../services/directSigning.ts'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())
vi.mock('@nextcloud/dialogs', () => ({ showSuccess: vi.fn() }))
vi.mock('../../../services/directSigning.ts', () => ({
	signPdfDirectly: vi.fn(),
	downloadSignedPdf: vi.fn(),
}))

const sign = vi.mocked(signPdfDirectly)
const download = vi.mocked(downloadSignedPdf)

describe('immediate signature confirmation', () => {
	beforeEach(() => vi.clearAllMocks())

	function dialog() {
		return mount(DirectSignDialog, {
			props: { name: 'document.pdf', nodeId: 42 },
			global: { stubs: {
					NcDialog: { name: 'NcDialog', props: ['buttons'], template: '<div><slot /></div>' },
					NcNoteCard: { name: 'NcNoteCard', template: '<div><slot /></div>' },
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
		expect(buttons(wrapper)[1].label).toBe('Sign now')
		const pdf = new Blob(['signed'], { type: 'application/pdf' })
		sign.mockResolvedValueOnce({ pdf, filename: 'document.pdf' })
		await buttons(wrapper)[1].callback()
		expect(sign).toHaveBeenCalledExactlyOnceWith(expect.objectContaining({ name: 'document.pdf', nodeId: 42 }))
		expect(download).toHaveBeenCalledExactlyOnceWith(pdf, 'document.pdf')
		expect(showSuccess).toHaveBeenCalledOnce()
		expect(wrapper.emitted('close')?.[0]).toEqual([true])
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
		await buttons(wrapper)[1].callback()
		await nextTick()
		expect(wrapper.text()).toContain('Could not sign this PDF')
		expect(wrapper.emitted('close')).toBeUndefined()
		expect(download).not.toHaveBeenCalled()
	})

	it('blocks retries after server errors because the request may have been persisted', async () => {
		const wrapper = dialog()
		sign.mockRejectedValueOnce({ response: { status: 500 } })
		await buttons(wrapper)[1].callback()
		expect(buttons(wrapper)[1].disabled).toBe(true)
	})

	it('blocks retries if the network outcome is unknown', async () => {
		const wrapper = dialog()
		sign.mockRejectedValueOnce(new Error('connection lost'))
		await buttons(wrapper)[1].callback()
		expect(buttons(wrapper)[1].disabled).toBe(true)
		expect(wrapper.text()).toContain('signing result is unknown')
		await buttons(wrapper)[1].callback()
		expect(sign).toHaveBeenCalledTimes(1)
	})
})
