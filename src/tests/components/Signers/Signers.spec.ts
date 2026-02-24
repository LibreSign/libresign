/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import type { Pinia } from 'pinia'
type SignersComponent = typeof import('../../../components/Signers/Signers.vue').default
let Signers: SignersComponent
import { useFilesStore } from '../../../store/files.js'

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn(),
}))

beforeAll(async () => {
	;({ default: Signers } = await import('../../../components/Signers/Signers.vue'))
})


describe('Signers', () => {
	let wrapper: VueWrapper<unknown> | null
	let filesStore: ReturnType<typeof useFilesStore>
	let pinia: Pinia

	const createWrapper = (props: Partial<{ event: string }> = {}) => {
		return mount(Signers, {
			props: {
				event: '',
				...props,
			},
			global: {
				plugins: [pinia],
				stubs: {
					Signer: true,
					draggable: true,
				},
			},
		})
	}

	beforeEach(() => {
		pinia = createPinia()
		setActivePinia(pinia)
		filesStore = useFilesStore()
		filesStore.selectedFile = { signers: [] }
		filesStore.getFile = () => filesStore.selectedFile
		filesStore.canSave = vi.fn(() => true)
		if (wrapper) {
			wrapper.unmount()
			wrapper = null
		}
		vi.clearAllMocks()
	})

	describe('RULE: signers returns signers from file store', () => {
		it('returns signers array from selected file', () => {
			filesStore.selectedFile = {
				signers: [
					{ id: 1, displayName: 'Alice' },
					{ id: 2, displayName: 'Bob' },
				],
			}
			wrapper = createWrapper()

			expect(wrapper.vm.signers).toEqual([
				{ id: 1, displayName: 'Alice' },
				{ id: 2, displayName: 'Bob' },
			])
		})

		it('returns undefined when no file selected', () => {
			filesStore.selectedFile = {}
			wrapper = createWrapper()

			expect(wrapper.vm.signers).toBeUndefined()
		})
	})

	describe('RULE: sortableSigners getter and setter modify file signers', () => {
		it('getter returns signers', () => {
			filesStore.selectedFile = {
				signers: [{ id: 1 }],
			}
			wrapper = createWrapper()

			expect(wrapper.vm.sortableSigners).toEqual([{ id: 1 }])
		})

		it('setter updates file signers', async () => {
			filesStore.selectedFile = {
				signers: [{ id: 1 }],
			}
			wrapper = createWrapper()

			wrapper.vm.sortableSigners = [{ id: 2 }, { id: 3 }]

			expect(filesStore.selectedFile.signers).toEqual([{ id: 2 }, { id: 3 }])
		})
	})

	describe('RULE: isOrderedNumeric checks if signatureFlow is ordered_numeric', () => {
		it('returns true for ordered_numeric string', () => {
			filesStore.selectedFile = {
				signatureFlow: 'ordered_numeric',
				signers: [],
			}
			wrapper = createWrapper()

			expect(wrapper.vm.isOrderedNumeric).toBe(true)
		})

		it('returns false for parallel string', () => {
			filesStore.selectedFile = {
				signatureFlow: 'parallel',
				signers: [],
			}
			wrapper = createWrapper()

			expect(wrapper.vm.isOrderedNumeric).toBe(false)
		})

		it('returns true for numeric value 2', () => {
			filesStore.selectedFile = {
				signatureFlow: 2,
				signers: [],
			}
			wrapper = createWrapper()

			expect(wrapper.vm.isOrderedNumeric).toBe(true)
		})

		it('returns false for numeric value 1 (parallel)', () => {
			filesStore.selectedFile = {
				signatureFlow: 1,
				signers: [],
			}
			wrapper = createWrapper()

			expect(wrapper.vm.isOrderedNumeric).toBe(false)
		})

		it('returns false for numeric value 0 (none)', () => {
			filesStore.selectedFile = {
				signatureFlow: 0,
				signers: [],
			}
			wrapper = createWrapper()

			expect(wrapper.vm.isOrderedNumeric).toBe(false)
		})

		it('returns false when signatureFlow undefined', () => {
			filesStore.selectedFile = {
				signers: [],
			}
			wrapper = createWrapper()

			expect(wrapper.vm.isOrderedNumeric).toBe(false)
		})
	})

	describe('RULE: canReorder requires canSave and multiple signers', () => {
		it('returns true when can save and has multiple signers', () => {
			filesStore.selectedFile = {
				signers: [{}, {}],
			}
			filesStore.canSave = vi.fn().mockReturnValue(true)
			wrapper = createWrapper()

			expect(wrapper.vm.canReorder).toBe(true)
		})

		it('returns false when cannot save', () => {
			filesStore.selectedFile = {
				signers: [{}, {}],
			}
			filesStore.canSave = vi.fn().mockReturnValue(false)
			wrapper = createWrapper()

			expect(wrapper.vm.canReorder).toBe(false)
		})

		it('returns false with single signer', () => {
			filesStore.selectedFile = {
				signers: [{}],
			}
			filesStore.canSave = vi.fn().mockReturnValue(true)
			wrapper = createWrapper()

			expect(wrapper.vm.canReorder).toBe(false)
		})

		it('returns false with no signers', () => {
			filesStore.selectedFile = {
				signers: [],
			}
			filesStore.canSave = vi.fn().mockReturnValue(true)
			wrapper = createWrapper()

			expect(wrapper.vm.canReorder).toBe(false)
		})
	})

	describe('RULE: onDragEnd recalculates signing orders sequentially', () => {
		it('updates signing orders starting from 1', () => {
			filesStore.selectedFile = {
				signers: [
					{ signingOrder: 1 },
					{ signingOrder: 2 },
					{ signingOrder: 3 },
				],
			}
			wrapper = createWrapper()

			wrapper.vm.onDragEnd({ oldIndex: 0, newIndex: 2 })

			expect(filesStore.selectedFile.signers[0].signingOrder).toBe(1)
			expect(filesStore.selectedFile.signers[1].signingOrder).toBe(2)
			expect(filesStore.selectedFile.signers[2].signingOrder).toBe(3)
		})

		it('emits signing-order-changed event', async () => {
			filesStore.selectedFile = {
				signers: [
					{ signingOrder: 1 },
					{ signingOrder: 2 },
				],
			}
			wrapper = createWrapper()

			wrapper.vm.onDragEnd({ oldIndex: 0, newIndex: 1 })

			expect(wrapper.emitted('signing-order-changed')).toBeTruthy()
		})

		it('does not emit when dragged to same position', () => {
			filesStore.selectedFile = {
				signers: [
					{ signingOrder: 1 },
					{ signingOrder: 2 },
				],
			}
			wrapper = createWrapper()

			wrapper.vm.onDragEnd({ oldIndex: 1, newIndex: 1 })

			expect(wrapper.emitted('signing-order-changed')).toBeFalsy()
		})

		it('handles out of order initial values', () => {
			filesStore.selectedFile = {
				signers: [
					{ signingOrder: 5 },
					{ signingOrder: 10 },
					{ signingOrder: 7 },
				],
			}
			wrapper = createWrapper()

			wrapper.vm.onDragEnd({ oldIndex: 1, newIndex: 2 })

			expect(filesStore.selectedFile.signers[0].signingOrder).toBe(1)
			expect(filesStore.selectedFile.signers[1].signingOrder).toBe(2)
			expect(filesStore.selectedFile.signers[2].signingOrder).toBe(3)
		})

		it('handles many signers correctly', () => {
			filesStore.selectedFile = {
				signers: [
					{ signingOrder: 1 },
					{ signingOrder: 2 },
					{ signingOrder: 3 },
					{ signingOrder: 4 },
					{ signingOrder: 5 },
				],
			}
			wrapper = createWrapper()

			wrapper.vm.onDragEnd({ oldIndex: 0, newIndex: 4 })

			for (let i = 0; i < 5; i++) {
				expect(filesStore.selectedFile.signers[i].signingOrder).toBe(i + 1)
			}
		})
	})

	describe('RULE: component renders draggable when ordered_numeric and canReorder', () => {
		it('uses draggable component when conditions met', () => {
			filesStore.selectedFile = {
				signatureFlow: 'ordered_numeric',
				signers: [{}, {}],
			}
			filesStore.canSave = vi.fn().mockReturnValue(true)
			wrapper = createWrapper()

			expect(wrapper.vm.isOrderedNumeric).toBe(true)
			expect(wrapper.vm.canReorder).toBe(true)
		})

		it('uses regular ul when not ordered_numeric', () => {
			filesStore.selectedFile = {
				signatureFlow: 'parallel',
				signers: [{}, {}],
			}
			filesStore.canSave = vi.fn().mockReturnValue(true)
			wrapper = createWrapper()

			expect(wrapper.vm.isOrderedNumeric).toBe(false)
		})

		it('uses regular ul when cannot reorder', () => {
			filesStore.selectedFile = {
				signatureFlow: 'ordered_numeric',
				signers: [{}],
			}
			filesStore.canSave = vi.fn().mockReturnValue(true)
			wrapper = createWrapper()

			expect(wrapper.vm.canReorder).toBe(false)
		})
	})
})
