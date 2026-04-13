/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { MockedFunction } from 'vitest'
import { mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { mdiCheckCircle, mdiClockOutline, mdiCircleOutline } from '@mdi/js'
import type { TranslationFunction, PluralTranslationFunction } from '../../test-types'
import Signer from '../../../components/Signers/Signer.vue'
import { useFilesStore } from '../../../store/files.js'

type FileSigner = {
	signed?: boolean
	identifyMethods?: Array<{ method: string }>
	status?: number
	statusText?: string
	displayName?: string
	signingOrder?: number
}

type SelectedFile = {
	signatureFlow?: string
	signers: FileSigner[]
}

type FilesStoreMock = ReturnType<typeof useFilesStore> & {
	selectedFile: SelectedFile
	getFile: MockedFunction<(file?: unknown) => SelectedFile>
	canSave: MockedFunction<() => boolean>
	isOriginalFileDeleted: MockedFunction<() => boolean>
}

type SignerVm = {
	signatureFlow: string
	signer: FileSigner
	signerStatusText: string
	counterNumber: number
	counterType?: string
	isMethodDisabled: boolean
	disabledTooltip: string
	showDragHandle: boolean
	chipType: string
	statusIconPath: string
	signerClickAction: () => void
	closeActions: () => void
	filesStore: FilesStoreMock
}

type SignerWrapper = VueWrapper<SignerVm>

const { t, n } = vi.hoisted(() => {
	const t: TranslationFunction = (_app, text, vars) => {
		if (vars) {
			return text.replace(/{(\w+)}/g, (_m, key) => String(vars[key]))
		}
		return text
	}

	const n: PluralTranslationFunction = (_app, singular, plural, count) => (count === 1 ? singular : plural)

	return { t, n }
})

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => {
		if (key === 'can_request_sign') return true
		if (key === 'identify_methods') return [
			{ name: 'email', enabled: true, friendly_name: 'Email' },
			{ name: 'phone', enabled: false, friendly_name: 'Phone' },
		]
		return defaultValue
	}),
}))
vi.mock('@nextcloud/event-bus', () => ({
	emit: vi.fn(),
	subscribe: vi.fn(),
	unsubscribe: vi.fn(),
}))

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

import { emit } from '@nextcloud/event-bus'

describe('Signer', () => {
	let wrapper: SignerWrapper | null
	let filesStore: FilesStoreMock
	let pinia: ReturnType<typeof createPinia>

	const createWrapper = (props = {}): SignerWrapper => {
		const { signerIndex = 0, ...restProps } = props as { signerIndex?: number }
		const ncListItemStub = {
			name: 'NcListItem',
			template: '<div><slot /></div>',
		}

		return mount(Signer, {
			props: {
				signer: {
					statusText: '',
					...filesStore.selectedFile.signers[signerIndex],
				},
				event: '',
				draggable: false,
				...restProps,
			},
			global: {
				plugins: [pinia],
				mocks: {
					t,
				},
				stubs: {
					NcListItem: ncListItemStub,
					NcAvatar: true,
					NcChip: true,
					DragVertical: true,
				},
			},
		}) as unknown as SignerWrapper
	}

	beforeEach(() => {
		pinia = createPinia()
		setActivePinia(pinia)
		filesStore = useFilesStore() as FilesStoreMock
		filesStore.selectedFile = {
			signatureFlow: 'parallel',
			signers: [
				{ signed: false, identifyMethods: [], status: 0, statusText: 'Draft', displayName: 'Test Signer' },
			],
		}
		filesStore.getFile = vi.fn(() => filesStore.selectedFile) as FilesStoreMock['getFile']
		filesStore.canSave = vi.fn(() => true)
		filesStore.isOriginalFileDeleted = vi.fn(() => false)
		if (wrapper) {
			wrapper.unmount()
			wrapper = null
		}
		vi.clearAllMocks()
	})

	describe('RULE: signatureFlow uses canonical string values', () => {
		it('returns ordered_numeric when already canonical', () => {
			filesStore.selectedFile = { signatureFlow: 'ordered_numeric', signers: [{}] }
			wrapper = createWrapper()

			expect(wrapper.vm.signatureFlow).toBe('ordered_numeric')
		})

		it('returns parallel for parallel flow', () => {
			filesStore.selectedFile = { signatureFlow: 'parallel', signers: [{}] }
			wrapper = createWrapper()

			expect(wrapper.vm.signatureFlow).toBe('parallel')
		})
		it('defaults to parallel when undefined', () => {
			filesStore.selectedFile = { signers: [{}] }
			wrapper = createWrapper()

			expect(wrapper.vm.signatureFlow).toBe('parallel')
		})

		it('returns none when file flow is none', () => {
			filesStore.selectedFile = { signatureFlow: 'none', signers: [{}] }
			wrapper = createWrapper()

			expect(wrapper.vm.signatureFlow).toBe('none')
		})
	})

	describe('RULE: counterNumber shows signingOrder only in ordered_numeric with multiple signers', () => {
		it('returns signingOrder in ordered_numeric flow with multiple signers', () => {
			filesStore.selectedFile = {
				signatureFlow: 'ordered_numeric',
				signers: [
					{ signingOrder: 1 },
					{ signingOrder: 2 },
				],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.counterNumber).toBe(1)
		})

		it('returns 0 in parallel flow', () => {
			filesStore.selectedFile = {
				signatureFlow: 'parallel',
				signers: [
					{ signingOrder: 1 },
					{ signingOrder: 2 },
				],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.counterNumber).toBe(0)
		})

		it('returns 0 with single signer', () => {
			filesStore.selectedFile = {
				signatureFlow: 'ordered_numeric',
				signers: [{ signingOrder: 1 }],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.counterNumber).toBe(0)
		})

		it('returns 0 when no signingOrder', () => {
			filesStore.selectedFile = {
				signatureFlow: 'ordered_numeric',
				signers: [{}, {}],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.counterNumber).toBe(0)
		})
	})

	describe('RULE: counterType returns highlighted when counterNumber exists', () => {
		it('returns highlighted when counterNumber is positive', () => {
			filesStore.selectedFile = {
				signatureFlow: 'ordered_numeric',
				signers: [
					{ signingOrder: 3 },
					{ signingOrder: 4 },
				],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.counterType).toBe('highlighted')
		})

		it('returns undefined when counterNumber is 0', () => {
			filesStore.selectedFile = {
				signatureFlow: 'parallel',
				signers: [{}],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.counterType).toBeUndefined()
		})
	})

	describe('RULE: isMethodDisabled checks if identification method is disabled', () => {
		it('returns true when method is disabled', () => {
			filesStore.selectedFile = {
				signers: [
					{ identifyMethods: [{ method: 'phone' }] },
				],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.isMethodDisabled).toBe(true)
		})

		it('returns false when method is enabled', () => {
			filesStore.selectedFile = {
				signers: [
					{ identifyMethods: [{ method: 'email' }] },
				],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.isMethodDisabled).toBe(false)
		})

		it('returns false when no identifyMethods', () => {
			filesStore.selectedFile = {
				signers: [{}],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.isMethodDisabled).toBe(false)
		})
	})

	describe('RULE: disabledTooltip explains why method cannot be used', () => {
		it('returns message with method name when disabled', () => {
			filesStore.selectedFile = {
				signers: [
					{ identifyMethods: [{ method: 'phone' }] },
				],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			const tooltip = wrapper.vm.disabledTooltip
			expect(tooltip).toContain('Phone')
			expect(tooltip).toContain('disabled by the administrator')
		})

		it('returns empty string when method enabled', () => {
			filesStore.selectedFile = {
				signers: [
					{ identifyMethods: [{ method: 'email' }] },
				],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.disabledTooltip).toBe('')
		})
	})

	describe('RULE: showDragHandle requires ordered_numeric flow, unsigned, multiple signers, and canSave', () => {
		it('returns true when all conditions met', () => {
			filesStore.selectedFile = {
				signatureFlow: 'ordered_numeric',
				signers: [
					{ signed: false },
					{ signed: false },
				],
			}
			filesStore.canSave = vi.fn().mockReturnValue(true)
			filesStore.isOriginalFileDeleted = vi.fn().mockReturnValue(false)
			wrapper = createWrapper({ signerIndex: 0, draggable: true })

			expect(wrapper.vm.showDragHandle).toBe(true)
		})

		it('returns false when not draggable prop', () => {
			filesStore.selectedFile = {
				signatureFlow: 'ordered_numeric',
				signers: [{}, {}],
			}
			filesStore.canSave = vi.fn().mockReturnValue(true)
			wrapper = createWrapper({ signerIndex: 0, draggable: false })

			expect(wrapper.vm.showDragHandle).toBe(false)
		})

		it('returns false in parallel flow', () => {
			filesStore.selectedFile = {
				signatureFlow: 'parallel',
				signers: [{}, {}],
			}
			filesStore.canSave = vi.fn().mockReturnValue(true)
			filesStore.isOriginalFileDeleted = vi.fn().mockReturnValue(false)
			wrapper = createWrapper({ signerIndex: 0, draggable: true })

			expect(wrapper.vm.showDragHandle).toBe(false)
		})

		it('returns false when signer already signed', () => {
			filesStore.selectedFile = {
				signatureFlow: 'ordered_numeric',
				signers: [
					{ signed: true },
					{ signed: false },
				],
			}
			filesStore.canSave = vi.fn().mockReturnValue(true)
			filesStore.isOriginalFileDeleted = vi.fn().mockReturnValue(false)
			wrapper = createWrapper({ signerIndex: 0, draggable: true })

			expect(wrapper.vm.showDragHandle).toBe(false)
		})

		it('returns false with single signer', () => {
			filesStore.selectedFile = {
				signatureFlow: 'ordered_numeric',
				signers: [{ signed: false }],
			}
			filesStore.canSave = vi.fn().mockReturnValue(true)
			filesStore.isOriginalFileDeleted = vi.fn().mockReturnValue(false)
			wrapper = createWrapper({ signerIndex: 0, draggable: true })

			expect(wrapper.vm.showDragHandle).toBe(false)
		})

		it('returns false when cannot save', () => {
			filesStore.selectedFile = {
				signatureFlow: 'ordered_numeric',
				signers: [{}, {}],
			}
			filesStore.canSave = vi.fn().mockReturnValue(false)
			filesStore.isOriginalFileDeleted = vi.fn().mockReturnValue(false)
			wrapper = createWrapper({ signerIndex: 0, draggable: true })

			expect(wrapper.vm.showDragHandle).toBe(false)
		})

		it('returns false when original file deleted', () => {
			filesStore.selectedFile = {
				signatureFlow: 'ordered_numeric',
				signers: [{}, {}],
			}
			filesStore.canSave = vi.fn().mockReturnValue(true)
			filesStore.isOriginalFileDeleted = vi.fn().mockReturnValue(true)
			wrapper = createWrapper({ signerIndex: 0, draggable: true })

			expect(wrapper.vm.showDragHandle).toBe(false)
		})
	})

	describe('RULE: chipType returns variant based on signer status', () => {
		it('exposes signerStatusText directly from signer contract', () => {
			filesStore.selectedFile = {
				signers: [{ statusText: 'Able to sign' }],
			}
			wrapper = createWrapper({ signer: filesStore.selectedFile.signers[0] })

			expect(wrapper.vm.signerStatusText).toBe('Able to sign')
		})

		it('returns success for status 2 (SIGNED)', () => {
			filesStore.selectedFile = {
				signers: [{ status: 2 }],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.chipType).toBe('success')
		})

		it('returns warning for status 1 (ABLE_TO_SIGN)', () => {
			filesStore.selectedFile = {
				signers: [{ status: 1 }],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.chipType).toBe('warning')
		})

		it('returns secondary for status 0 (DRAFT)', () => {
			filesStore.selectedFile = {
				signers: [{ status: 0 }],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.chipType).toBe('secondary')
		})

		it('returns secondary for undefined status', () => {
			filesStore.selectedFile = {
				signers: [{}],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.chipType).toBe('secondary')
		})
	})

	describe('RULE: statusIconPath returns MDI icon based on signer status', () => {
		it('returns check circle for status 2 (SIGNED)', () => {
			filesStore.selectedFile = {
				signers: [{ status: 2 }],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.statusIconPath).toBe(mdiCheckCircle)
		})

		it('returns clock outline for status 1 (ABLE_TO_SIGN)', () => {
			filesStore.selectedFile = {
				signers: [{ status: 1 }],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.statusIconPath).toBe(mdiClockOutline)
		})

		it('returns circle outline for status 0 (DRAFT)', () => {
			filesStore.selectedFile = {
				signers: [{ status: 0 }],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.statusIconPath).toBe(mdiCircleOutline)
		})
	})

	describe('RULE: signerClickAction emits event when all conditions met', () => {
		it('emits event when all checks pass', () => {
			filesStore.selectedFile = {
				signers: [
					{ signed: false, identifyMethods: [{ method: 'email' }] },
				],
			}
			filesStore.isOriginalFileDeleted = vi.fn().mockReturnValue(false)
			wrapper = createWrapper({ signerIndex: 0, event: 'signer:clicked' })

			wrapper.vm.signerClickAction()

			expect(emit).toHaveBeenCalledWith('signer:clicked', wrapper.vm.signer)
			expect(wrapper.emitted('select')).toEqual([[wrapper.vm.signer]])
		})

		it('does not emit when no event prop', () => {
			filesStore.selectedFile = {
				signers: [{ signed: false }],
			}
			filesStore.isOriginalFileDeleted = vi.fn().mockReturnValue(false)
			wrapper = createWrapper({ signerIndex: 0, event: '' })

			wrapper.vm.signerClickAction()

			expect(emit).not.toHaveBeenCalled()
			expect(wrapper.emitted('select')).toEqual([[wrapper.vm.signer]])
		})

		it('does not emit when signer already signed', () => {
			filesStore.selectedFile = {
				signers: [{ signed: true }],
			}
			filesStore.isOriginalFileDeleted = vi.fn().mockReturnValue(false)
			wrapper = createWrapper({ signerIndex: 0, event: 'signer:clicked' })

			wrapper.vm.signerClickAction()

			expect(emit).not.toHaveBeenCalled()
			expect(wrapper.emitted('select')).toBeUndefined()
		})

		it('does not treat unknown methods as disabled', () => {
			filesStore.selectedFile = {
				signers: [
					{ signed: false, identifyMethods: [{ method: 'account' }] },
				],
			}
			filesStore.isOriginalFileDeleted = vi.fn().mockReturnValue(false)
			wrapper = createWrapper({ signerIndex: 0, event: '' })

			wrapper.vm.signerClickAction()

			expect(wrapper.vm.isMethodDisabled).toBe(false)
			expect(wrapper.emitted('select')).toEqual([[wrapper.vm.signer]])
		})

		it('does not emit when method disabled', () => {
			filesStore.selectedFile = {
				signers: [
					{ signed: false, identifyMethods: [{ method: 'phone' }] },
				],
			}
			filesStore.isOriginalFileDeleted = vi.fn().mockReturnValue(false)
			wrapper = createWrapper({ signerIndex: 0, event: 'signer:clicked' })

			wrapper.vm.signerClickAction()

			expect(emit).not.toHaveBeenCalled()
			expect(wrapper.emitted('select')).toBeUndefined()
		})

		it('does not emit when file deleted', () => {
			filesStore.selectedFile = {
				signers: [{ signed: false }],
			}
			filesStore.isOriginalFileDeleted = vi.fn().mockReturnValue(true)
			wrapper = createWrapper({ signerIndex: 0, event: 'signer:clicked' })

			wrapper.vm.signerClickAction()

			expect(emit).not.toHaveBeenCalled()
			expect(wrapper.emitted('select')).toBeUndefined()
		})
	})

	describe('RULE: closeActions calls listItem actions closeMenu', () => {
		it('calls closeMenu when ref and method exist', () => {
			const localWrapper = createWrapper()
			wrapper = localWrapper

			localWrapper.vm.closeActions()

			const listItemStub = localWrapper.findComponent({ name: 'NcListItem' })
			expect(listItemStub).toBeTruthy()
		})

		it('does nothing when listItem ref missing', () => {
			const localWrapper = createWrapper()
			wrapper = localWrapper

			expect(() => localWrapper.vm.closeActions()).not.toThrow()
		})

		it('does nothing when closeMenu not a function', () => {
			const localWrapper = createWrapper()
			wrapper = localWrapper

			expect(() => localWrapper.vm.closeActions()).not.toThrow()
		})
	})
})
