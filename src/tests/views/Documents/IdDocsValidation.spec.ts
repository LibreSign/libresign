/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

import IdDocsValidation from '../../../views/Documents/IdDocsValidation.vue'
import { FILE_STATUS } from '../../../constants.js'
import type { components } from '../../../types/openapi/openapi'

const axiosGetMock = vi.fn()
const axiosDeleteMock = vi.fn()
const showErrorMock = vi.fn()
const openDocumentMock = vi.fn()
const routerPushMock = vi.fn()
const userConfigUpdateMock = vi.fn()

const userConfigStore = {
	id_docs_filters: {
		owner: '',
		status: null,
	},
	id_docs_sort: {
		sortBy: 'owner',
		sortOrder: 'DESC',
	},
	update: vi.fn((...args: unknown[]) => userConfigUpdateMock(...args)),
}

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn((...args: unknown[]) => axiosGetMock(...args)),
		delete: vi.fn((...args: unknown[]) => axiosDeleteMock(...args)),
	},
}))

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn((...args: unknown[]) => showErrorMock(...args)),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string, params?: Record<string, string | number>) => {
		let resolvedPath = path
		for (const [key, value] of Object.entries(params || {})) {
			resolvedPath = resolvedPath.replace(`{${key}}`, String(value))
		}
		return `/ocs/v2.php${resolvedPath}`
	}),
}))

vi.mock('vue-router', () => ({
	useRouter: vi.fn(() => ({
		push: routerPushMock,
	})),
}))

vi.mock('../../../store/userconfig.js', () => ({
	useUserConfigStore: vi.fn(() => userConfigStore),
}))

vi.mock('../../../utils/viewer.js', () => ({
	openDocument: vi.fn((...args: unknown[]) => openDocumentMock(...args)),
}))

vi.mock('@nextcloud/vue/components/NcActions', () => ({
	default: { name: 'NcActions', template: '<div class="nc-actions-stub"><slot /><slot name="icon" /></div>' },
}))

vi.mock('@nextcloud/vue/components/NcActionButton', () => ({
	default: {
		name: 'NcActionButton',
		emits: ['click', 'update:modelValue'],
		template: '<button class="nc-action-button-stub" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
	},
}))

vi.mock('@nextcloud/vue/components/NcActionInput', () => ({
	default: {
		name: 'NcActionInput',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template: '<input class="nc-action-input-stub" />',
	},
}))

vi.mock('@nextcloud/vue/components/NcActionSeparator', () => ({
	default: { name: 'NcActionSeparator', template: '<hr class="nc-action-separator-stub" />' },
}))

vi.mock('@nextcloud/vue/components/NcAvatar', () => ({
	default: { name: 'NcAvatar', template: '<div class="nc-avatar-stub" />' },
}))

vi.mock('@nextcloud/vue/components/NcEmptyContent', () => ({
	default: { name: 'NcEmptyContent', template: '<div class="nc-empty-content-stub"><slot /><slot name="icon" /></div>' },
}))

vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
	default: { name: 'NcLoadingIcon', template: '<span class="nc-loading-icon-stub" />' },
}))

vi.mock('@nextcloud/vue/components/NcIconSvgWrapper', () => ({
	default: { name: 'NcIconSvgWrapper', template: '<i class="nc-icon-stub" />' },
}))

describe('IdDocsValidation.vue', () => {
	type IdDocEntry = components['schemas']['File']

	const signedDoc: IdDocEntry = {
		account: {
			userId: 'alice',
			displayName: 'Alice',
		},
		file_type: {
			type: 'passport',
			name: 'Passport',
			description: null,
		},
		created_at: '2026-03-06T10:00:00Z',
		file: {
			uuid: 'file-1',
			status: FILE_STATUS.SIGNED,
			statusText: 'Signed',
			name: 'alice-passport.pdf',
			created_at: '2026-03-06T10:00:00Z',
			file: {
				type: 'application/pdf',
				nodeId: 10,
				signedNodeId: 10,
				url: '/files/alice-passport.pdf',
			},
			callback: null,
			signers: [{
				description: null,
				displayName: 'Approver',
				request_sign_date: '2026-03-06T10:00:00Z',
				signed: '2026-03-06T12:00:00Z',
				sign_date: '2026-03-06T12:00:00Z',
				me: false,
				signRequestId: 1,
				status: 2,
				statusText: 'Signed',
				visibleElements: [],
				uid: 'approver',
			}],
		},
	}

	const pendingDoc: IdDocEntry = {
		account: {
			userId: 'bob',
			displayName: 'Bob',
		},
		file_type: {
			type: 'driver-license',
			name: 'Driver License',
			description: null,
		},
		created_at: '2026-03-07T10:00:00Z',
		file: {
			uuid: 'file-2',
			status: FILE_STATUS.ABLE_TO_SIGN,
			statusText: 'Pending',
			name: 'bob-license.pdf',
			created_at: '2026-03-07T10:00:00Z',
			file: {
				type: 'application/pdf',
				nodeId: 11,
				signedNodeId: 11,
				url: '/files/bob-license.pdf',
			},
			callback: null,
			signers: [],
		},
	}

	const createWrapper = () => mount(IdDocsValidation)

	beforeEach(() => {
		axiosGetMock.mockReset()
		axiosDeleteMock.mockReset()
		showErrorMock.mockReset()
		openDocumentMock.mockReset()
		routerPushMock.mockReset()
		userConfigUpdateMock.mockReset()
		userConfigStore.update.mockClear()
		userConfigStore.id_docs_filters = { owner: '', status: null }
		userConfigStore.id_docs_sort = { sortBy: 'owner', sortOrder: 'DESC' }

		axiosGetMock.mockResolvedValue({
			data: {
				ocs: {
					data: {
						pagination: {
							total: 2,
							current: null,
							next: null,
							prev: null,
							last: null,
							first: null,
						},
						data: [signedDoc, pendingDoc],
					},
				},
			},
		})

		axiosDeleteMock.mockResolvedValue({
			data: {
				ocs: {
					data: {
						success: true,
					},
				},
			},
		})
	})

	it('loads documents on mount using saved sort', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		expect(axiosGetMock).toHaveBeenCalledWith('/ocs/v2.php/apps/libresign/api/v1/id-docs/approval/list', {
			params: {
				page: 1,
				length: 50,
				sortBy: 'owner',
				sortOrder: 'DESC',
			},
		})
		expect(wrapper.vm.documentList).toHaveLength(2)
		expect(wrapper.vm.hasMore).toBe(false)
	})

	it('filters by owner and status and persists filter changes', async () => {
		vi.useFakeTimers()
		const wrapper = createWrapper()
		await flushPromises()

		wrapper.vm.filters.owner = 'bob'
		wrapper.vm.setStatusFilter('pending', true)
		vi.runAllTimers()
		await flushPromises()

		expect(wrapper.vm.hasActiveFilters).toBe(true)
		expect(wrapper.vm.activeFilterCount).toBe(2)
		expect(wrapper.vm.filteredDocuments).toEqual([pendingDoc])
		expect(userConfigUpdateMock).toHaveBeenCalledWith('id_docs_filters', {
			owner: 'bob',
			status: 'pending',
		})

		vi.useRealTimers()
	})

	it('toggles sort direction and then clears the sort for the same column', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		await wrapper.vm.sortColumn('owner')
		expect(wrapper.vm.sortOrder).toBe('ASC')

		await wrapper.vm.sortColumn('owner')
		expect(wrapper.vm.sortBy).toBeNull()
		expect(wrapper.vm.sortOrder).toBeNull()
		expect(userConfigUpdateMock).toHaveBeenCalledWith('id_docs_sort', {
			sortBy: null,
			sortOrder: null,
		})
	})

	it('routes to approve and validation pages using document uuid', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		wrapper.vm.openApprove(pendingDoc)
		wrapper.vm.openValidationURL(signedDoc)

		expect(routerPushMock).toHaveBeenNthCalledWith(1, {
			name: 'IdDocsApprove',
			params: { uuid: 'file-2' },
			query: { idDocApproval: 'true' },
		})
		expect(routerPushMock).toHaveBeenNthCalledWith(2, {
			name: 'ValidationFile',
			params: { uuid: 'file-1' },
		})
	})

	it('opens the file in the viewer and reports missing urls', async () => {
		const wrapper = createWrapper()
		await flushPromises()
		const missingUrlDoc: IdDocEntry = {
			...signedDoc,
			file: {
				...signedDoc.file,
				name: 'missing.pdf',
				file: {
					...signedDoc.file.file,
					nodeId: 12,
					url: '',
				},
			},
		}

		wrapper.vm.openFile(signedDoc)
		wrapper.vm.openFile(missingUrlDoc)

		expect(openDocumentMock).toHaveBeenCalledWith({
			fileUrl: '/files/alice-passport.pdf',
			filename: 'alice-passport.pdf',
			nodeId: 10,
		})
		expect(showErrorMock).toHaveBeenCalledWith('File not found')
	})

	it('deletes a document and reloads the list', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		await wrapper.vm.deleteDocument(signedDoc)
		await flushPromises()

		expect(axiosDeleteMock).toHaveBeenCalledWith('/ocs/v2.php/apps/libresign/api/v1/id-docs/10')
		expect(axiosGetMock).toHaveBeenCalledTimes(2)
	})
})
