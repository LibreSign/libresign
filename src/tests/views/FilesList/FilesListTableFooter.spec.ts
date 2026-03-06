/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import FilesListTableFooter from '../../../views/FilesList/FilesListTableFooter.vue'

const filesStoreMock = {
	files: {} as Record<string, unknown>,
	loading: false,
}

const filtersStoreMock = {
	activeChips: [] as unknown[],
}

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string, params?: Record<string, string | number>) => {
		if (!params) {
			return text
		}

		return Object.entries(params).reduce((message, [key, value]) => {
			return message.replace(`{${key}}`, String(value))
		}, text)
	}),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

vi.mock('../../../store/files.js', () => ({
	useFilesStore: vi.fn(() => filesStoreMock),
}))

vi.mock('../../../store/filters.js', () => ({
	useFiltersStore: vi.fn(() => filtersStoreMock),
}))

describe('FilesListTableFooter.vue', () => {
	beforeEach(() => {
		filesStoreMock.files = {}
		filesStoreMock.loading = false
		filtersStoreMock.activeChips = []
	})

	function createWrapper() {
		return mount(FilesListTableFooter)
	}

	it('summarizes a single file in singular form', () => {
		filesStoreMock.files = { 1: { id: 1 } }
		const wrapper = createWrapper()

		expect(wrapper.vm.totalFiles).toBe(1)
		expect(wrapper.vm.summary).toBe('1 file')
		expect(wrapper.vm.haveFiles).toBe(true)
	})

	it('summarizes multiple files in plural form', () => {
		filesStoreMock.files = { 1: { id: 1 }, 2: { id: 2 }, 3: { id: 3 } }
		const wrapper = createWrapper()

		expect(wrapper.vm.summary).toBe('3 files')
	})

	it('hides the footer summary while files are loading', () => {
		filesStoreMock.files = { 1: { id: 1 } }
		filesStoreMock.loading = true
		const wrapper = createWrapper()

		expect(wrapper.vm.haveFiles).toBe(false)
	})

	it('shows the row when active filter chips exist even without files', () => {
		filtersStoreMock.activeChips = [{ id: 'signed' }]
		const wrapper = createWrapper()

		expect(wrapper.find('tr').isVisible()).toBe(true)
	})
})