/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import { createL10nMock } from '../testHelpers/l10n.js'

import RenewEmail from '../../views/RenewEmail.vue'

const loadStateMock = vi.fn((_: string, key: string) => {
	const values: Record<string, string> = {
		title: 'Renew access',
		body: 'Please confirm the email renewal.',
		renewButton: 'Renew email',
		uuid: 'request-uuid',
	}

	return values[key]
})

vi.mock('@nextcloud/l10n', () => createL10nMock())

vi.mock('@nextcloud/axios', () => ({
	default: {
		post: vi.fn(),
	},
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app: string, key: string) => loadStateMock(app, key)),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string, params?: Record<string, string>) => path.replace('{uuid}', params?.uuid ?? '')),
}))

describe('RenewEmail.vue', () => {
	beforeEach(() => {
		vi.mocked(axios.post).mockReset()
		loadStateMock.mockClear()
	})

	function createWrapper() {
		return mount(RenewEmail, {
			global: {
				stubs: {
					NcButton: {
						name: 'NcButton',
						props: ['variant', 'wide', 'disabled'],
						emits: ['click'],
						template: '<button class="renew-button" :disabled="disabled" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
					},
					NcIconSvgWrapper: {
						name: 'NcIconSvgWrapper',
						props: ['path'],
						template: '<span class="icon-stub"></span>',
					},
					NcLoadingIcon: {
						name: 'NcLoadingIcon',
						props: ['size'],
						template: '<span class="loading-stub"></span>',
					},
					NcNoteCard: {
						name: 'NcNoteCard',
						props: ['type'],
						template: '<div class="note-card"><slot /></div>',
					},
				},
			},
		})
	}

	it('renders the initial state from the server-side payload', () => {
		const wrapper = createWrapper()

		expect(wrapper.text()).toContain('Renew access')
		expect(wrapper.text()).toContain('Please confirm the email renewal.')
		expect(wrapper.text()).toContain('Renew email')
	})

	it('stores the success message after renewing the email', async () => {
		vi.mocked(axios.post).mockResolvedValue({
			data: {
				ocs: {
					data: {
						message: 'Email renewed successfully',
					},
				},
			},
		})
		const wrapper = createWrapper()

		await wrapper.vm.renew()

		expect(wrapper.vm.response).toBe('Email renewed successfully')
		expect(wrapper.vm.error).toBe('')
		expect(wrapper.vm.hasLoading).toBe(false)
	})

	it('stores the error message when the renewal request fails', async () => {
		vi.mocked(axios.post).mockRejectedValue({
			response: {
				data: {
					ocs: {
						data: {
							message: 'Renewal failed',
						},
					},
				},
			},
		})
		const wrapper = createWrapper()

		await wrapper.vm.renew()

		expect(wrapper.vm.error).toBe('Renewal failed')
		expect(wrapper.vm.response).toBe('')
		expect(wrapper.vm.hasLoading).toBe(false)
	})
})
