/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'

import FileEntryGrid from '../../../views/FilesList/FileEntry/FileEntryGrid.vue'

type FileEntryGridVm = InstanceType<typeof FileEntryGrid> & {
	openDetailsIfAvailable: (event?: Event) => void
}

const actionsMenuStoreMock = {
	opened: null as number | null,
}

const filesStoreMock = {
	loading: true,
	selectFile: vi.fn(),
}

const sidebarStoreMock = {
	activeRequestSignatureTab: vi.fn(),
}

vi.mock('@nextcloud/vue/components/NcDateTime', () => ({
	default: {
		name: 'NcDateTime',
		template: '<span class="nc-datetime-stub"></span>',
		props: ['timestamp', 'ignoreSeconds'],
	},
}))

vi.mock('../../../views/FilesList/FileEntry/FileEntryActions.vue', () => ({
	default: {
		name: 'FileEntryActions',
		template: '<div class="actions-stub"></div>',
		props: ['source', 'loading', 'opened'],
	},
}))

vi.mock('../../../views/FilesList/FileEntry/FileEntryCheckbox.vue', () => ({
	default: {
		name: 'FileEntryCheckbox',
		template: '<td class="checkbox-stub"></td>',
		props: ['isLoading', 'source'],
	},
}))

vi.mock('../../../views/FilesList/FileEntry/FileEntryName.vue', () => ({
	default: {
		name: 'FileEntryName',
		template: '<div class="name-stub"></div>',
		props: ['basename', 'extension'],
	},
}))

vi.mock('../../../views/FilesList/FileEntry/FileEntryPreview.vue', () => ({
	default: {
		name: 'FileEntryPreview',
		template: '<div class="preview-stub"></div>',
		props: ['source'],
	},
}))

vi.mock('../../../views/FilesList/FileEntry/FileEntrySigners.vue', () => ({
	default: {
		name: 'FileEntrySigners',
		template: '<div class="signers-stub"></div>',
		props: ['signersCount', 'signers'],
	},
}))

vi.mock('../../../views/FilesList/FileEntry/FileEntryStatus.vue', () => ({
	default: {
		name: 'FileEntryStatus',
		template: '<div class="status-stub"></div>',
		props: ['status', 'statusText', 'signers'],
	},
}))

vi.mock('../../../store/actionsmenu.js', () => ({
	useActionsMenuStore: vi.fn(() => actionsMenuStoreMock),
}))

vi.mock('../../../store/files.js', () => ({
	useFilesStore: vi.fn(() => filesStoreMock),
}))

vi.mock('../../../store/sidebar.js', () => ({
	useSidebarStore: vi.fn(() => sidebarStoreMock),
}))

describe('FileEntryGrid.vue', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		actionsMenuStoreMock.opened = null
		filesStoreMock.loading = true
		filesStoreMock.selectFile.mockReset()
		sidebarStoreMock.activeRequestSignatureTab.mockReset()
	})

	function createWrapper() {
		return mount(FileEntryGrid, {
			props: {
				source: {
					id: 7,
					name: 'agreement.pdf',
					status: 3,
					statusText: 'Pending',
					signers: [{ displayName: 'Ada Lovelace' }],
					signersCount: 1,
					created_at: '2026-03-06T10:00:00Z',
				},
				loading: false,
			},
		})
	}

	it('renders the file entry row', () => {
		const wrapper = createWrapper()

		expect(wrapper.find('tr.files-list__row').exists()).toBe(true)
	})

	it('passes the file name and extension to the name entry', () => {
		const wrapper = createWrapper()
		const name = wrapper.findComponent({ name: 'FileEntryName' })

		expect(name.props('basename')).toBe('agreement.pdf')
		expect(name.props('extension')).toBe('.pdf')
	})

	it('uses the files store loading state for the checkbox', () => {
		const wrapper = createWrapper()
		const checkbox = wrapper.findComponent({ name: 'FileEntryCheckbox' })

		expect(checkbox.props('isLoading')).toBe(true)
		expect(checkbox.props('source')).toMatchObject({ id: 7 })
	})

	it('opens the details sidebar for the selected file', () => {
		const wrapper = createWrapper()
		const vm = wrapper.vm as FileEntryGridVm
		const event = {
			preventDefault: vi.fn(),
			stopPropagation: vi.fn(),
		} as unknown as Event

		vm.openDetailsIfAvailable(event)

		expect(filesStoreMock.selectFile).toHaveBeenCalledWith(7)
		expect(sidebarStoreMock.activeRequestSignatureTab).toHaveBeenCalled()
	})
})
