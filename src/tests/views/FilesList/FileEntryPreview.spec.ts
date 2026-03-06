/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import FileEntryPreview from '../../../views/FilesList/FileEntry/FileEntryPreview.vue'

const userConfigStoreMock = {
	files_list_grid_view: false,
}

vi.mock('@nextcloud/router', () => ({
	generateUrl: vi.fn((path: string, params?: Record<string, string | number>) => {
		if (!params) {
			return path
		}

		return Object.entries(params).reduce((result, [key, value]) => {
			return result.replace(`{${key}}`, String(value))
		}, path)
	}),
	generateOcsUrl: vi.fn((path: string, params?: Record<string, string | number>) => {
		const resolved = params
			? Object.entries(params).reduce((result, [key, value]) => result.replace(`{${key}}`, String(value)), path)
			: path
		return `https://cloud.example${resolved}`
	}),
}))

vi.mock('../../../store/userconfig.js', () => ({
	useUserConfigStore: vi.fn(() => userConfigStoreMock),
}))

describe('FileEntryPreview.vue', () => {
	beforeEach(() => {
		userConfigStoreMock.files_list_grid_view = false
	})

	function createWrapper(source: Record<string, unknown>) {
		return mount(FileEntryPreview, {
			props: { source },
			global: {
				stubs: {
					NcIconSvgWrapper: true,
				},
			},
		})
	}

	it('detects envelope nodes', () => {
		const wrapper = createWrapper({ nodeType: 'envelope' })

		expect(wrapper.vm.isEnvelope).toBe(true)
	})

	it('builds a thumbnail URL from the node id with list-size previews', () => {
		const wrapper = createWrapper({ nodeId: 42 })
		const url = wrapper.vm.previewUrl as URL

		expect(url.toString()).toContain('/apps/libresign/api/v1/file/thumbnail/42')
		expect(url.searchParams.get('x')).toBe('32')
		expect(url.searchParams.get('y')).toBe('32')
		expect(url.searchParams.get('a')).toBe('1')
	})

	it('uses grid preview sizes when the user config enables grid mode', () => {
		userConfigStoreMock.files_list_grid_view = true
		const wrapper = createWrapper({ id: 7 })
		const url = wrapper.vm.previewUrl as URL

		expect(url.toString()).toContain('/apps/libresign/api/v1/file/thumbnail/file_id/7')
		expect(url.searchParams.get('x')).toBe('128')
		expect(url.searchParams.get('y')).toBe('128')
	})

	it('disables the preview URL after a background load failure', async () => {
		const wrapper = createWrapper({ nodeId: 42 })

		wrapper.vm.backgroundFailed = true
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.previewUrl).toBe(null)
	})
})