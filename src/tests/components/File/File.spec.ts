/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import File from '../../../components/File/File.vue'

type FileEntry = {
	id: number
	nodeId?: number
	name: string
	status: number
	statusText: string
}

const filesStoreMock = {
	selectedFileId: 7,
	files: {
		7: {
			id: 13,
			nodeId: 99,
			name: 'contract.pdf',
			status: 3,
			statusText: 'Signed',
		},
	} as Record<number, FileEntry>,
	selectFile: vi.fn(),
}

const sidebarStoreMock = {
	activeRequestSignatureTab: vi.fn(),
}

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string, params?: Record<string, string | number>) => {
		if (path.includes('{nodeId}')) {
			return `https://cloud.example${path.replace('{nodeId}', String(params?.nodeId ?? ''))}`
		}
		return `https://cloud.example${path.replace('{fileId}', String(params?.fileId ?? ''))}`
	}),
	generateUrl: vi.fn((path: string, params?: Record<string, string | number>) => path.replace('{fileid}', String(params?.fileid ?? ''))),
}))

vi.mock('../../../store/files.js', () => ({
	useFilesStore: vi.fn(() => filesStoreMock),
}))

vi.mock('../../../store/sidebar.js', () => ({
	useSidebarStore: vi.fn(() => sidebarStoreMock),
}))

vi.mock('@nextcloud/vue/components/NcIconSvgWrapper', () => ({
	default: {
		name: 'NcIconSvgWrapper',
		template: '<i class="nc-icon-svg-wrapper-stub" />',
	},
}))

describe('File.vue', () => {
	const createWrapper = () => mount(File)

	beforeEach(() => {
		filesStoreMock.selectedFileId = 7
		filesStoreMock.files = {
			7: {
				id: 13,
				nodeId: 99,
				name: 'contract.pdf',
				status: 3,
				statusText: 'Signed',
			},
		}
		filesStoreMock.selectFile.mockReset()
		sidebarStoreMock.activeRequestSignatureTab.mockReset()
	})

	it('renders the selected file preview using the node id thumbnail endpoint', () => {
		const wrapper = createWrapper()

		const image = wrapper.find('img')

		expect(image.exists()).toBe(true)
		expect(wrapper.find('h1').text()).toBe('contract.pdf')
		expect(image.attributes('src')).toContain('/apps/libresign/api/v1/file/thumbnail/99')
		expect(image.attributes('src')).toContain('x=128')
		expect(image.attributes('src')).toContain('y=128')
		expect(image.attributes('src')).toContain('mimeFallback=true')
		expect(image.attributes('src')).toContain('a=0')
	})

	it('falls back to the file id thumbnail endpoint when node id is absent', () => {
		filesStoreMock.files[7] = {
			id: 13,
			name: 'fallback.pdf',
			status: 1,
			statusText: 'Pending',
		}

		const wrapper = createWrapper()

		expect(wrapper.find('img').attributes('src')).toContain('/apps/libresign/api/v1/file/thumbnail/file_id/13')
	})

	it('selects the file and opens the request signature sidebar on click', async () => {
		const wrapper = createWrapper()

		await wrapper.get('.content-file').trigger('click')

		expect(filesStoreMock.selectFile).toHaveBeenCalledWith(7)
		expect(sidebarStoreMock.activeRequestSignatureTab).toHaveBeenCalledTimes(1)
	})

	it('maps file status values to the expected CSS classes', () => {
		const wrapper = createWrapper()

		expect(wrapper.vm.statusToClass(0)).toBe('no-signers')
		expect(wrapper.vm.statusToClass(1)).toBe('pending')
		expect(wrapper.vm.statusToClass(2)).toBe('pending')
		expect(wrapper.vm.statusToClass(3)).toBe('signed')
		expect(wrapper.vm.statusToClass(999)).toBe('')
	})
})