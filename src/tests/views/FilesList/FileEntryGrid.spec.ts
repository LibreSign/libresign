/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import FileEntryGrid from '../../../views/FilesList/FileEntry/FileEntryGrid.vue'

const actionsMenuStoreMock = {
	opened: null as number | null,
}

const filesStoreMock = {
	loading: true,
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

vi.mock('../../../views/FilesList/FileEntry/FileEntryMixin.js', () => ({
	default: {
		props: {
			source: {
				type: Object,
				required: true,
			},
			loading: {
				type: Boolean,
				required: true,
			},
		},
		computed: {
			fileExtension() {
				return '.pdf'
			},
			mtime() {
				return new Date('2026-03-06T10:00:00Z')
			},
			mtimeOpacity() {
				return { color: 'red' }
			},
			openedMenu: {
				get() {
					return false
				},
				set() {},
			},
		},
		methods: {
			onRightClick: vi.fn(),
			openDetailsIfAvailable: vi.fn(),
		},
	},
}))

vi.mock('../../../store/actionsmenu.js', () => ({
	useActionsMenuStore: vi.fn(() => actionsMenuStoreMock),
}))

vi.mock('../../../store/files.js', () => ({
	useFilesStore: vi.fn(() => filesStoreMock),
}))

describe('FileEntryGrid.vue', () => {
	beforeEach(() => {
		actionsMenuStoreMock.opened = null
		filesStoreMock.loading = true
	})

	function createWrapper() {
		return mount(FileEntryGrid, {
			props: {
				source: {
					id: 7,
					name: 'agreement.pdf',
					status: 3,
					statusText: 'Pending',
					signers: [{ id: 1 }],
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
})
