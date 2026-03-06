/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import FileEntryActions from '../../../views/FilesList/FileEntry/FileEntryActions.vue'

const openDocumentMock = vi.fn()

const actionsMenuStoreMock = {
	opened: null as number | null,
}

const filesStoreMock = {
	files: {
		1: {
			id: 1,
			uuid: 'file-uuid',
			name: 'contract.pdf',
			nodeId: 17,
			nodeType: 'file',
			signers: [{ me: true, sign_uuid: 'sign-uuid' }],
		},
	},
	canSign: vi.fn(() => true),
	canValidate: vi.fn(() => true),
	canDelete: vi.fn(() => true),
	isOriginalFileDeleted: vi.fn(() => false),
	selectFile: vi.fn(),
	getAllFiles: vi.fn(async () => ({
		1: { id: 1, uuid: 'file-uuid' },
	})),
	delete: vi.fn(async () => undefined),
	rename: vi.fn(async () => undefined),
}

const sidebarStoreMock = {
	hideSidebar: vi.fn(),
	activeRequestSignatureTab: vi.fn(),
}

const signStoreMock = {
	setFileToSign: vi.fn(),
}

const routerPushMock = vi.fn()

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app: string, _key: string, defaultValue: unknown) => defaultValue),
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: vi.fn((path: string, params?: Record<string, string>) => path.replace('{uuid}', params?.uuid ?? '')),
}))

vi.mock('../../../utils/viewer.js', () => ({
	openDocument: vi.fn((...args: unknown[]) => openDocumentMock(...args)),
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

vi.mock('../../../store/sign.js', () => ({
	useSignStore: vi.fn(() => signStoreMock),
}))

vi.mock('@nextcloud/vue/components/NcActions', () => ({
	default: {
		name: 'NcActions',
		template: '<div class="nc-actions-stub"><slot /></div>',
	},
}))

vi.mock('@nextcloud/vue/components/NcActionButton', () => ({
	default: {
		name: 'NcActionButton',
		emits: ['click'],
		template: '<button class="nc-action-button-stub" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
	},
}))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: {
		name: 'NcButton',
		emits: ['click'],
		template: '<button class="nc-button-stub" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
	},
}))

vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
	default: {
		name: 'NcCheckboxRadioSwitch',
		template: '<label class="checkbox-radio-switch-stub"><slot /></label>',
	},
}))

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
	default: {
		name: 'NcDialog',
		template: '<div class="nc-dialog-stub"><slot /><slot name="actions" /></div>',
	},
}))

vi.mock('@nextcloud/vue/components/NcIconSvgWrapper', () => ({
	default: {
		name: 'NcIconSvgWrapper',
		template: '<i class="nc-icon-svg-wrapper-stub" />',
	},
}))

vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
	default: {
		name: 'NcLoadingIcon',
		template: '<span class="nc-loading-icon-stub" />',
	},
}))

describe('FileEntryActions.vue', () => {
	const source = {
		id: 1,
		uuid: 'file-uuid',
		name: 'contract.pdf',
		nodeId: 17,
		nodeType: 'file',
		signers: [{ me: true, sign_uuid: 'sign-uuid' }],
	}

	const createWrapper = () => mount(FileEntryActions, {
		props: {
			opened: false,
			source,
			loading: false,
		},
		global: {
			mocks: {
				$router: {
					push: routerPushMock,
				},
			},
		},
	})

	beforeEach(() => {
		actionsMenuStoreMock.opened = null
		filesStoreMock.files[1] = { ...source }
		filesStoreMock.canSign.mockReturnValue(true)
		filesStoreMock.canValidate.mockReturnValue(true)
		filesStoreMock.canDelete.mockReturnValue(true)
		filesStoreMock.isOriginalFileDeleted.mockReturnValue(false)
		filesStoreMock.selectFile.mockReset()
		filesStoreMock.getAllFiles.mockClear()
		filesStoreMock.delete.mockReset()
		filesStoreMock.rename.mockReset()
		sidebarStoreMock.hideSidebar.mockReset()
		sidebarStoreMock.activeRequestSignatureTab.mockReset()
		signStoreMock.setFileToSign.mockReset()
		routerPushMock.mockReset()
		openDocumentMock.mockReset()
	})

	it('registers the default action menu on mount', async () => {
		const wrapper = createWrapper()
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.enabledMenuActions.map((action: { id: string }) => action.id)).toEqual([
			'request-signature',
			'details',
			'rename',
			'validate',
			'sign',
			'delete',
			'open',
		])
	})

	it('shows request-signature instead of details when the file has no signers', async () => {
		const wrapper = createWrapper()
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.visibleIf({ id: 'request-signature' })).toBe(false)
		expect(wrapper.vm.visibleIf({ id: 'details' })).toBe(true)

		await wrapper.setProps({
			source: {
				...source,
				signers: [],
			},
		})

		expect(wrapper.vm.visibleIf({ id: 'request-signature' })).toBe(true)
		expect(wrapper.vm.visibleIf({ id: 'details' })).toBe(false)
	})

	it('opens the sign flow for the current signer', async () => {
		const wrapper = createWrapper()

		await wrapper.vm.onActionClick({ id: 'sign' })

		expect(sidebarStoreMock.hideSidebar).toHaveBeenCalledTimes(1)
		expect(filesStoreMock.getAllFiles).toHaveBeenCalledWith({
			signer_uuid: 'sign-uuid',
			force_fetch: true,
		})
		expect(signStoreMock.setFileToSign).toHaveBeenCalledWith({ id: 1, uuid: 'file-uuid' })
		expect(routerPushMock).toHaveBeenCalledWith({
			name: 'SignPDF',
			params: { uuid: 'sign-uuid' },
		})
		expect(filesStoreMock.selectFile).toHaveBeenCalledWith(1)
		expect(sidebarStoreMock.activeRequestSignatureTab).toHaveBeenCalledTimes(1)
	})

	it('routes validation, rename and open actions to the expected targets', async () => {
		const wrapper = createWrapper()

		await wrapper.vm.onActionClick({ id: 'validate' })
		await wrapper.vm.onActionClick({ id: 'rename' })
		await wrapper.vm.onActionClick({ id: 'open' })

		expect(routerPushMock).toHaveBeenCalledWith({
			name: 'ValidationFile',
			params: { uuid: 'file-uuid' },
		})
		expect(wrapper.emitted('start-rename')).toBeTruthy()
		expect(openDocumentMock).toHaveBeenCalledWith({
			fileUrl: '/apps/libresign/p/pdf/file-uuid',
			filename: 'contract.pdf',
			nodeId: 17,
		})
	})

	it('confirms deletion through the store and clears the deleting state', async () => {
		const wrapper = createWrapper()

		wrapper.vm.confirmDelete = true
		wrapper.vm.deleteFile = false
		await wrapper.vm.doDelete()

		expect(filesStoreMock.delete).toHaveBeenCalledWith(source, false)
		expect(wrapper.vm.deleting).toBe(false)
	})
})