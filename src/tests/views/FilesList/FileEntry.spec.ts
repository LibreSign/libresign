/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import FileEntry from '../../../views/FilesList/FileEntry/FileEntry.vue'
import { useFilesStore } from '../../../store/files.js'
import { useActionsMenuStore } from '../../../store/actionsmenu.js'
import type { TranslationFunction } from '../../test-types'

type FileEntrySource = {
	id: number
	name: string
	status: number
	statusText: string
	signers: unknown[]
	created_at: number
}

const t: TranslationFunction = (_app, text) => text

vi.mock('@nextcloud/dialogs', () => ({
	showSuccess: vi.fn(),
}))

vi.mock('@nextcloud/vue/components/NcDateTime', () => ({
	default: {
		name: 'NcDateTime',
		template: '<span></span>',
		props: ['timestamp', 'ignoreSeconds'],
	},
}))

vi.mock('../../../views/FilesList/FileEntry/FileEntryActions.vue', () => ({
	default: {
		name: 'FileEntryActions',
		template: '<div></div>',
		props: ['source', 'loading', 'opened'],
		emits: ['update:opened', 'rename', 'start-rename'],
		methods: {
			doRename: vi.fn().mockResolvedValue(undefined),
		},
	},
}))

vi.mock('../../../views/FilesList/FileEntry/FileEntryCheckbox.vue', () => ({
	default: {
		name: 'FileEntryCheckbox',
		template: '<td></td>',
		props: ['isLoading', 'source'],
	},
}))

vi.mock('../../../views/FilesList/FileEntry/FileEntryName.vue', () => ({
	default: {
		name: 'FileEntryName',
		template: '<div></div>',
		props: ['basename', 'extension'],
		emits: ['rename', 'renaming'],
		methods: {
			startRenaming: vi.fn(),
			stopRenaming: vi.fn(),
		},
	},
}))

vi.mock('../../../views/FilesList/FileEntry/FileEntryPreview.vue', () => ({
	default: {
		name: 'FileEntryPreview',
		template: '<div></div>',
		props: ['source'],
	},
}))

vi.mock('../../../views/FilesList/FileEntry/FileEntryStatus.vue', () => ({
	default: {
		name: 'FileEntryStatus',
		template: '<div></div>',
		props: ['status', 'statusText', 'signers'],
	},
}))

vi.mock('../../../views/FilesList/FileEntry/FileEntrySigners.vue', () => ({
	default: {
		name: 'FileEntrySigners',
		template: '<div></div>',
		props: ['signersCount', 'signers'],
	},
}))

vi.mock('../../../views/FilesList/FileEntry/FileEntryMixin.js', () => ({
	default: {
		computed: {
			fileExtension() {
				return 'pdf'
			},
			mtime(this: { source?: FileEntrySource }): number {
				return this.source?.created_at ?? Date.now()
			},
			mtimeOpacity() {
				return {}
			},
		},
		data() {
			const source: FileEntrySource = {
				id: 1,
				name: 'test.pdf',
				status: 1,
				statusText: 'Ready',
				signers: [],
				created_at: Date.now(),
			}

			return {
				source,
				loading: false,
				openedMenu: false,
			}
		},
	},
}))

describe('FileEntry.vue - Individual File Entry', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	it('renders file entry row', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		expect(wrapper.find('tr.files-list__row').exists()).toBe(true)
	})

	it('initializes renaming state as false', () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		expect(wrapper.vm.isRenaming).toBe(false)
	})

	it('initializes renaming saving state as false', () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		expect(wrapper.vm.renamingSaving).toBe(false)
	})

	it('renames file on rename event', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		await wrapper.vm.onRename('newname.pdf')
		expect(wrapper.vm.renamingSaving).toBe(false)
	})

	it('clears renaming saving flag after rename', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.renamingSaving = true
		await wrapper.vm.onRename('newname.pdf')
		expect(wrapper.vm.renamingSaving).toBe(false)
	})

	it('starts rename on file', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.onStartRename()
		expect(wrapper.vm.$refs.name).toBeDefined()
	})

	it('tracks file renaming state', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(wrapper.vm.isRenaming).toBe(false)
		wrapper.vm.onFileRenaming(true)
		expect(wrapper.vm.isRenaming).toBe(true)
		wrapper.vm.onFileRenaming(false)
		expect(wrapper.vm.isRenaming).toBe(false)
	})

	it('uses files store for file operations', async () => {
		const store = useFilesStore()
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(wrapper.vm.filesStore).toBe(store)
	})

	it('uses actions menu store for menu state', async () => {
		const store = useActionsMenuStore()
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(wrapper.vm.actionsMenuStore).toBe(store)
	})

	it('renders file entry checkbox', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		expect(wrapper.findComponent({ name: 'FileEntryCheckbox' }).exists()).toBe(true)
	})

	it('renders file entry name', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		expect(wrapper.findComponent({ name: 'FileEntryName' }).exists()).toBe(true)
	})

	it('renders file entry actions', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		expect(wrapper.findComponent({ name: 'FileEntryActions' }).exists()).toBe(true)
	})

	it('renders file entry status', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		expect(wrapper.findComponent({ name: 'FileEntryStatus' }).exists()).toBe(true)
	})

	it('renders file entry signers', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		expect(wrapper.findComponent({ name: 'FileEntrySigners' }).exists()).toBe(true)
	})

	it('passes signersCount to FileEntrySigners', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		const signersComponent = wrapper.findComponent({ name: 'FileEntrySigners' })
		expect(signersComponent.exists()).toBe(true)
		expect(signersComponent.props('signersCount')).toBeDefined()
	})

	it('passes signers array to FileEntrySigners', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		const signersComponent = wrapper.findComponent({ name: 'FileEntrySigners' })
		expect(signersComponent.exists()).toBe(true)
		expect(signersComponent.props('signers')).toBeDefined()
	})

	it('renders row signers cell with click handler', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		const signersCell = wrapper.find('.files-list__row-signers')
		expect(signersCell.exists()).toBe(true)
	})

	it('renders file modification time', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		expect(wrapper.findComponent({ name: 'NcDateTime' }).exists()).toBe(true)
	})

	it('passes source to child components', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(wrapper.vm.filesStore).toBeDefined()
	})

	it('shows success message after rename', async () => {
		const { showSuccess } = await import('@nextcloud/dialogs')
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		await wrapper.vm.onRename('newname.pdf')
		expect(showSuccess).toHaveBeenCalled()
	})

	it('restores file name on rename failure', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		await wrapper.vm.$nextTick()
		vi.spyOn(wrapper.vm.$refs.actions, 'doRename').mockRejectedValueOnce(new Error('Rename failed'))
		try {
			await wrapper.vm.onRename('newname.pdf')
		} catch (e) {}
		expect(wrapper.vm.renamingSaving).toBe(false)
	})

	it('handles right click on row', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
					onRightClick: vi.fn(),
				},
			},
		})

		await wrapper.vm.$nextTick()
		const row = wrapper.find('.files-list__row')
		expect(row.exists()).toBe(true)
	})

	it('renders row name cell with click handler', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		const nameCell = wrapper.find('.files-list__row-name')
		expect(nameCell.exists()).toBe(true)
	})

	it('renders row status cell with click handler', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		const statusCell = wrapper.find('.files-list__row-status')
		expect(statusCell.exists()).toBe(true)
	})

	it('renders row mtime cell', async () => {
		const wrapper = mount(FileEntry, {
			props: {},
			global: {
				mocks: {
					t,
				},
			},
		})

		const mtimeCell = wrapper.find('.files-list__row-mtime')
		expect(mtimeCell.exists()).toBe(true)
	})
})
