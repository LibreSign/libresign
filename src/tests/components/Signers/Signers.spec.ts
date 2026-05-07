/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import type { Pinia } from 'pinia'
import Signers from '../../../components/Signers/Signers.vue'
import { useFilesStore } from '../../../store/files.js'
import type { SignatureFlowValue } from '../../../types/index'

type FilesStore = ReturnType<typeof useFilesStore>
type StoreFile = FilesStore['getFile'] extends (...args: any[]) => infer TResult ? TResult : never

type SignerRecord = {
	localKey?: string
	displayName?: string
	signed?: string | null | boolean | unknown[]
	signingOrder?: number
	[key: string]: unknown
}

type SelectedFile = Partial<StoreFile> & {
	signers?: SignerRecord[]
	signatureFlow?: SignatureFlowValue | null
}

type FilesStoreMock = FilesStore & {
	selectedFile: SelectedFile
	getFile: ReturnType<typeof vi.fn<(file?: unknown) => StoreFile>>
	canSave: ReturnType<typeof vi.fn<() => boolean>>
}

type SignersVm = {
	signers?: SignerRecord[]
	sortableSigners?: SignerRecord[]
	isOrderedNumeric: boolean
	canReorder: boolean
	onDragEnd: (evt: { oldIndex: number; newIndex: number }) => void
}

type SignersWrapper = VueWrapper<SignersVm>

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn(),
}))


describe('Signers', () => {
	let wrapper: SignersWrapper | null
	let filesStore: FilesStoreMock
	let pinia: Pinia

	const createWrapper = (props: Partial<{ event: string }> = {}): SignersWrapper => {
		return shallowMount(Signers, {
			props: {
				event: '',
				...props,
			},
			global: {
				plugins: [pinia],
				stubs: {
					Signer: true,
					// Stub for vuedraggable 4 (Vue 3) which uses #item slot per element
					draggable: {
						name: 'draggable',
						template: `
							<div>
								<template v-for="(element, index) in modelValue" :key="index">
									<slot name="item" :element="element" :index="index" />
								</template>
							</div>
						`,
						props: ['modelValue', 'itemKey', 'tag', 'handle', 'class', 'chosenClass', 'dragClass'],
					},
				},
			},
		}) as unknown as SignersWrapper
	}

	beforeEach(() => {
		pinia = createPinia()
		setActivePinia(pinia)
		filesStore = useFilesStore() as FilesStoreMock
		filesStore.selectedFile = { signers: [] }
		filesStore.getFile = vi.fn(() => (filesStore.selectedFile || { signers: [] }) as StoreFile)
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
					{ localKey: 'signer:1', displayName: 'Alice' },
					{ localKey: 'signer:2', displayName: 'Bob' },
				],
			}
			wrapper = createWrapper()

			expect(wrapper.vm.signers).toEqual([
				{ localKey: 'signer:1', displayName: 'Alice' },
				{ localKey: 'signer:2', displayName: 'Bob' },
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
				signers: [{ localKey: 'signer:1' }],
			}
			wrapper = createWrapper()

			expect(wrapper.vm.sortableSigners).toEqual([{ localKey: 'signer:1' }])
		})

		it('setter updates file signers', async () => {
			filesStore.selectedFile = {
				signers: [{ localKey: 'signer:1' }],
			}
			wrapper = createWrapper()

			wrapper.vm.sortableSigners = [{ localKey: 'signer:2' }, { localKey: 'signer:3' }]

			expect(filesStore.selectedFile.signers).toEqual([{ localKey: 'signer:2' }, { localKey: 'signer:3' }])
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
				signatureFlow: 'ordered_numeric',
				signers: [],
			}
			wrapper = createWrapper()

			expect(wrapper.vm.isOrderedNumeric).toBe(true)
		})

		it('returns false for numeric value 1 (parallel)', () => {
			filesStore.selectedFile = {
				signatureFlow: 'parallel',
				signers: [],
			}
			wrapper = createWrapper()

			expect(wrapper.vm.isOrderedNumeric).toBe(false)
		})

		it('returns false for numeric value 0 (none)', () => {
			filesStore.selectedFile = {
				signatureFlow: 'none',
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
				signers: [{ localKey: 'a' }, { localKey: 'b' }],
			}
			filesStore.canSave = vi.fn().mockReturnValue(true)
			wrapper = createWrapper()

			expect(wrapper.vm.canReorder).toBe(true)
		})

		it('returns false when cannot save', () => {
			filesStore.selectedFile = {
				signers: [{ localKey: 'a' }, { localKey: 'b' }],
			}
			filesStore.canSave = vi.fn().mockReturnValue(false)
			wrapper = createWrapper()

			expect(wrapper.vm.canReorder).toBe(false)
		})

		it('returns false with single signer', () => {
			filesStore.selectedFile = {
				signers: [{ localKey: 'a' }],
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

			expect(filesStore.selectedFile.signers![0].signingOrder).toBe(1)
			expect(filesStore.selectedFile.signers![1].signingOrder).toBe(2)
			expect(filesStore.selectedFile.signers![2].signingOrder).toBe(3)
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

			expect(filesStore.selectedFile.signers![0].signingOrder).toBe(1)
			expect(filesStore.selectedFile.signers![1].signingOrder).toBe(2)
			expect(filesStore.selectedFile.signers![2].signingOrder).toBe(3)
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
				expect(filesStore.selectedFile.signers![i].signingOrder).toBe(i + 1)
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

	describe('RULE: signer.localKey is the sole :key', () => {
		it('renders one Signer stub per signer when all have localKey set', () => {
			filesStore.selectedFile = {
				signers: [
					{ localKey: 'abc-1', displayName: 'Alice' },
					{ localKey: 'abc-2', displayName: 'Bob' },
					{ localKey: 'abc-3', displayName: 'Carol' },
				],
			}
			wrapper = createWrapper()

			const signerStubs = wrapper.findAllComponents({ name: 'Signer' })
			expect(signerStubs).toHaveLength(3)
		})

		it('renders one Signer stub per signer in ordered_numeric mode with localKey', () => {
			filesStore.selectedFile = {
				signatureFlow: 'ordered_numeric',
				signers: [
					{ localKey: 'x-10', signingOrder: 1 },
					{ localKey: 'x-20', signingOrder: 2 },
				],
			}
			filesStore.canSave = vi.fn().mockReturnValue(true)
			wrapper = createWrapper()

			const signerStubs = wrapper.findAllComponents({ name: 'Signer' })
			expect(signerStubs).toHaveLength(2)
		})
	})
})
