/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import Request from '../../views/Request.vue'

const filesStoreMock = {
	selectedFileId: 0,
	disableIdentifySigner: vi.fn(),
	selectFile: vi.fn(),
}

const sidebarStoreMock = {
	isVisible: false,
}

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

vi.mock('../../store/files.js', () => ({
	useFilesStore: vi.fn(() => filesStoreMock),
}))

vi.mock('../../store/sidebar.js', () => ({
	useSidebarStore: vi.fn(() => sidebarStoreMock),
}))

describe('Request.vue', () => {
	beforeEach(() => {
		filesStoreMock.selectedFileId = 0
		filesStoreMock.disableIdentifySigner.mockReset()
		filesStoreMock.selectFile.mockReset()
		sidebarStoreMock.isVisible = false
	})

	function createWrapper() {
		return mount(Request, {
			global: {
				stubs: {
					File: { template: '<div class="file-stub" />' },
					ReqestPicker: { template: '<div class="request-picker-stub" />' },
				},
			},
		})
	}

	it('disables identify signer on mount', () => {
		createWrapper()

		expect(filesStoreMock.disableIdentifySigner).toHaveBeenCalledTimes(1)
	})

	it('shows the request picker when the sidebar is hidden', () => {
		const wrapper = createWrapper()

		expect(wrapper.find('.request-picker-stub').exists()).toBe(true)
	})

	it('hides helper text and request picker when the sidebar is visible', () => {
		sidebarStoreMock.isVisible = true
		const wrapper = createWrapper()

		expect(wrapper.text()).not.toContain('Choose the file to request signatures.')
		expect(wrapper.find('.request-picker-stub').exists()).toBe(false)
	})

	it('resets the selected file on unmount', () => {
		const wrapper = createWrapper()

		wrapper.unmount()

		expect(filesStoreMock.selectFile).toHaveBeenCalledTimes(1)
	})
})
