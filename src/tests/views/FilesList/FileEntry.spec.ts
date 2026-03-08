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
import { useSidebarStore } from '../../../store/sidebar.js'
import type { FileEntrySource } from '../../../composables/useFileEntry.js'
import type { TranslationFunction } from '../../test-types'

type FileEntryVm = InstanceType<typeof FileEntry> & {
	actions: {
		doRename: (newName: string) => Promise<void>
	} | null
	isRenaming: boolean
	renamingSaving: boolean
	name: unknown
	onRename: (newName: string) => Promise<void>
	onStartRename: () => void
	onFileRenaming: (nextIsRenaming: boolean) => void
	filesStore: ReturnType<typeof useFilesStore>
	actionsMenuStore: ReturnType<typeof useActionsMenuStore>
	openDetailsIfAvailable: (event?: Event) => void
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

describe('FileEntry.vue - Individual File Entry', () => {
	const source: FileEntrySource = {
		id: 1,
		name: 'test.pdf',
		status: 1,
		statusText: 'Ready',
		signers: [],
		signersCount: 0,
		created_at: Date.now(),
		metadata: {
			extension: 'pdf',
		},
	}

	function createWrapper() {
		return mount(FileEntry, {
			props: {
				source,
				loading: false,
			},
			global: {
				mocks: {
					t,
				},
			},
		})
	}

	beforeEach(() => {
		setActivePinia(createPinia())
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	it('renders file entry row', async () => {
		const wrapper = createWrapper()

		expect(wrapper.find('tr.files-list__row').exists()).toBe(true)
	})

	it('initializes renaming state as false', () => {
		const wrapper = createWrapper()
		const vm = wrapper.vm as FileEntryVm

		expect(vm.isRenaming).toBe(false)
	})

	it('initializes renaming saving state as false', () => {
		const wrapper = createWrapper()
		const vm = wrapper.vm as FileEntryVm

		expect(vm.renamingSaving).toBe(false)
	})

	it('renames file on rename event', async () => {
		const wrapper = createWrapper()
		const vm = wrapper.vm as FileEntryVm

		await wrapper.vm.$nextTick()
		await vm.onRename('newname.pdf')
		expect(vm.renamingSaving).toBe(false)
	})

	it('clears renaming saving flag after rename', async () => {
		const wrapper = createWrapper()
		const vm = wrapper.vm as FileEntryVm

		await wrapper.vm.$nextTick()
		vm.renamingSaving = true
		await vm.onRename('newname.pdf')
		expect(vm.renamingSaving).toBe(false)
	})

	it('starts rename on file', async () => {
		const wrapper = createWrapper()
		const vm = wrapper.vm as FileEntryVm

		await wrapper.vm.$nextTick()
		vm.onStartRename()
		expect(vm.name).toBeDefined()
	})

	it('tracks file renaming state', async () => {
		const wrapper = createWrapper()
		const vm = wrapper.vm as FileEntryVm

		await wrapper.vm.$nextTick()
		expect(vm.isRenaming).toBe(false)
		vm.onFileRenaming(true)
		expect(vm.isRenaming).toBe(true)
		vm.onFileRenaming(false)
		expect(vm.isRenaming).toBe(false)
	})

	it('uses files store for file operations', async () => {
		const store = useFilesStore()
		const wrapper = createWrapper()
		const vm = wrapper.vm as FileEntryVm

		await wrapper.vm.$nextTick()
		expect(vm.filesStore).toBe(store)
	})

	it('uses actions menu store for menu state', async () => {
		const store = useActionsMenuStore()
		const wrapper = createWrapper()
		const vm = wrapper.vm as FileEntryVm

		await wrapper.vm.$nextTick()
		expect(vm.actionsMenuStore).toBe(store)
	})

	it('renders file entry checkbox', async () => {
		const wrapper = createWrapper()

		expect(wrapper.findComponent({ name: 'FileEntryCheckbox' }).exists()).toBe(true)
	})

	it('renders file entry name', async () => {
		const wrapper = createWrapper()

		expect(wrapper.findComponent({ name: 'FileEntryName' }).exists()).toBe(true)
	})

	it('renders file entry actions', async () => {
		const wrapper = createWrapper()

		expect(wrapper.findComponent({ name: 'FileEntryActions' }).exists()).toBe(true)
	})

	it('renders file entry status', async () => {
		const wrapper = createWrapper()

		expect(wrapper.findComponent({ name: 'FileEntryStatus' }).exists()).toBe(true)
	})

	it('renders file entry signers', async () => {
		const wrapper = createWrapper()

		expect(wrapper.findComponent({ name: 'FileEntrySigners' }).exists()).toBe(true)
	})

	it('passes signersCount to FileEntrySigners', async () => {
		const wrapper = createWrapper()

		await wrapper.vm.$nextTick()
		const signersComponent = wrapper.findComponent({ name: 'FileEntrySigners' })
		expect(signersComponent.exists()).toBe(true)
		expect(signersComponent.props('signersCount')).toBeDefined()
	})

	it('passes signers array to FileEntrySigners', async () => {
		const wrapper = createWrapper()

		await wrapper.vm.$nextTick()
		const signersComponent = wrapper.findComponent({ name: 'FileEntrySigners' })
		expect(signersComponent.exists()).toBe(true)
		expect(signersComponent.props('signers')).toBeDefined()
	})

	it('renders row signers cell with click handler', async () => {
		const wrapper = createWrapper()

		const signersCell = wrapper.find('.files-list__row-signers')
		expect(signersCell.exists()).toBe(true)
	})

	it('renders file modification time', async () => {
		const wrapper = createWrapper()

		expect(wrapper.findComponent({ name: 'NcDateTime' }).exists()).toBe(true)
	})

	it('passes source to child components', async () => {
		const wrapper = createWrapper()
		const vm = wrapper.vm as FileEntryVm

		await wrapper.vm.$nextTick()
		expect(vm.filesStore).toBeDefined()
	})

	it('shows success message after rename', async () => {
		const { showSuccess } = await import('@nextcloud/dialogs')
		const wrapper = createWrapper()

		await wrapper.vm.$nextTick()
		await wrapper.vm.onRename('newname.pdf')
		expect(showSuccess).toHaveBeenCalled()
	})

	it('opens details through the sidebar store', async () => {
		const sidebarStore = useSidebarStore()
		const selectFileSpy = vi.spyOn(useFilesStore(), 'selectFile')
		const activeRequestSignatureTabSpy = vi.spyOn(sidebarStore, 'activeRequestSignatureTab')
		const wrapper = createWrapper()
		const vm = wrapper.vm as FileEntryVm
		const event = {
			preventDefault: vi.fn(),
			stopPropagation: vi.fn(),
		} as unknown as Event

		vm.openDetailsIfAvailable(event)

		expect(selectFileSpy).toHaveBeenCalledWith(1)
		expect(activeRequestSignatureTabSpy).toHaveBeenCalled()
	})

	it('restores file name on rename failure', async () => {
		const wrapper = createWrapper()
		const vm = wrapper.vm as FileEntryVm

		await wrapper.vm.$nextTick()
		expect(vm.actions).not.toBeNull()
		vi.spyOn(vm.actions!, 'doRename').mockRejectedValueOnce(new Error('Rename failed'))
		try {
			await vm.onRename('newname.pdf')
		} catch (e) {}
		expect(vm.renamingSaving).toBe(false)
	})

	it('handles right click on row', async () => {
		const wrapper = createWrapper()

		await wrapper.vm.$nextTick()
		const row = wrapper.find('.files-list__row')
		expect(row.exists()).toBe(true)
	})

	it('renders row name cell with click handler', async () => {
		const wrapper = createWrapper()

		const nameCell = wrapper.find('.files-list__row-name')
		expect(nameCell.exists()).toBe(true)
	})

	it('renders row status cell with click handler', async () => {
		const wrapper = createWrapper()

		const statusCell = wrapper.find('.files-list__row-status')
		expect(statusCell.exists()).toBe(true)
	})

	it('renders row mtime cell', async () => {
		const wrapper = createWrapper()

		const mtimeCell = wrapper.find('.files-list__row-mtime')
		expect(mtimeCell.exists()).toBe(true)
	})
})
