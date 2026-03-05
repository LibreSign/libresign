/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { mount } from '@vue/test-utils'

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => defaultValue),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path) => `/ocs/v2.php/apps/libresign${path}`),
	generateUrl: vi.fn((path) => `/apps/libresign${path}`),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
	},
}))

vi.mock('@nextcloud/capabilities', () => ({
	getCapabilities: vi.fn(() => ({})),
}))

vi.mock('../../../components/PdfEditor/PdfEditor.vue', () => ({
	default: {
		name: 'PdfEditor',
		render() {
			return null
		},
	},
}))

vi.mock('../../store/files.js', () => {
	const filesInstance = {
		getAllFiles: vi.fn(),
		addFile: vi.fn(),
		selectFile: vi.fn(),
		getFile: vi.fn(),
	}
	return {
		useFilesStore: vi.fn(() => filesInstance),
	}
})

vi.mock('../../store/sidebar.js', () => {
	const sidebarInstance = {
		toggleSidebar: vi.fn(),
		hideSidebar: vi.fn(),
		activeSignTab: vi.fn(),
	}
	return {
		useSidebarStore: vi.fn(() => sidebarInstance),
	}
})

vi.mock('../../store/sign.js', async () => {
	const actual = await vi.importActual('../../store/sign.js')
	return actual
})

describe('SignPDF.vue', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('attaches envelope files to signStore document', async () => {
		const SignPDF = (await import('../../../views/SignPDF/SignPDF.vue')).default
		const { useSignStore } = await import('../../../store/sign.js')
		const signStore = useSignStore()

		signStore.document = {
			id: 1,
			nodeType: 'envelope',
			files: [],
		}

		const wrapper = mount(SignPDF, {
			global: {
				stubs: {
					TopBar: true,
					PdfEditor: true,
					NcNoteCard: true,
					NcButton: true,
				},
				mocks: {
					$route: { name: 'SignPDFExternal', params: { uuid: 'uuid-123' }, query: {} },
				},
			},
		})

		const envelopeFiles = [
			{ id: 10, name: 'file1', metadata: { extension: 'pdf' } },
			{ id: 11, name: 'file2', metadata: { extension: 'pdf' } },
		]

		wrapper.vm.fetchEnvelopeFiles = vi.fn().mockResolvedValue(envelopeFiles)
		wrapper.vm.handleInitialStatePdfs = vi.fn()

		await wrapper.vm.loadEnvelopePdfs(1)

		expect(signStore.document.files).toEqual(envelopeFiles)
	})
})
