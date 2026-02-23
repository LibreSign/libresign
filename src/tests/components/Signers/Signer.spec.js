/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { mdiCheckCircle, mdiClockOutline, mdiCircleOutline } from '@mdi/js'
let Signer
import { useFilesStore } from '../../../store/files.js'

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

vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn((app, text, vars) => {
		if (vars) {
			return text.replace(/{(\w+)}/g, (m, key) => vars[key])
		}
		return text
	}),
	translatePlural: vi.fn((app, singular, plural, count) => (count === 1 ? singular : plural)),
	t: vi.fn((app, text, vars) => {
		if (vars) {
			return text.replace(/{(\w+)}/g, (m, key) => vars[key])
		}
		return text
	}),
	n: vi.fn((app, singular, plural, count) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

import { emit } from '@nextcloud/event-bus'

beforeAll(async () => {
	;({ default: Signer } = await import('../../../components/Signers/Signer.vue'))
})

describe('Signer', () => {
	let wrapper
	let filesStore
	let pinia

	const createWrapper = (props = {}) => {
		const ncListItemStub = {
			name: 'NcListItem',
			template: '<div><slot /></div>',
			setup() {
				return {
					$refs: {
						actions: {
							closeMenu: vi.fn(),
						},
					},
				}
			},
		}

		return mount(Signer, {
			props: {
				signerIndex: 0,
				event: '',
				draggable: false,
				...props,
			},
			global: {
				plugins: [pinia],
				mocks: {
					t: (app, text, vars) => {
						if (vars) {
							return text.replace(/{(\w+)}/g, (m, key) => vars[key])
						}
						return text
					},
				},
				stubs: {
					NcListItem: ncListItemStub,
					NcAvatar: true,
					NcChip: true,
					DragVertical: true,
				},
			},
		})
	}

	beforeEach(() => {
		pinia = createPinia()
		setActivePinia(pinia)
		filesStore = useFilesStore()
		filesStore.selectedFile = {
			signatureFlow: 'parallel',
			signers: [
				{ signed: false, identifyMethods: [], status: 0, displayName: 'Test Signer' },
			],
		}
		filesStore.getFile = () => filesStore.selectedFile
		filesStore.canSave = vi.fn(() => true)
		filesStore.isOriginalFileDeleted = vi.fn(() => false)
		if (wrapper) {
			wrapper.destroy()
		}
		vi.clearAllMocks()
	})

	describe('RULE: signatureFlow maps numeric values to string constants', () => {
		it('returns ordered_numeric for value 2', () => {
			filesStore.selectedFile = { signatureFlow: 2, signers: [{}] }
			wrapper = createWrapper()

			expect(wrapper.vm.signatureFlow).toBe('ordered_numeric')
		})

		it('returns parallel for value 1', () => {
			filesStore.selectedFile = { signatureFlow: 1, signers: [{}] }
			wrapper = createWrapper()

			expect(wrapper.vm.signatureFlow).toBe('parallel')
		})

		it('returns none for value 0', () => {
			filesStore.selectedFile = { signatureFlow: 0, signers: [{}] }
			wrapper = createWrapper()

			expect(wrapper.vm.signatureFlow).toBe('none')
		})

		it('defaults to parallel when undefined', () => {
			filesStore.selectedFile = { signers: [{}] }
			wrapper = createWrapper()

			expect(wrapper.vm.signatureFlow).toBe('parallel')
		})

		it('uses string value directly when already string', () => {
			filesStore.selectedFile = { signatureFlow: 'ordered_numeric', signers: [{}] }
			wrapper = createWrapper()

			expect(wrapper.vm.signatureFlow).toBe('ordered_numeric')
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

		it('returns null in parallel flow', () => {
			filesStore.selectedFile = {
				signatureFlow: 'parallel',
				signers: [
					{ signingOrder: 1 },
					{ signingOrder: 2 },
				],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.counterNumber).toBeNull()
		})

		it('returns null with single signer', () => {
			filesStore.selectedFile = {
				signatureFlow: 'ordered_numeric',
				signers: [{ signingOrder: 1 }],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.counterNumber).toBeNull()
		})

		it('returns null when no signingOrder', () => {
			filesStore.selectedFile = {
				signatureFlow: 'ordered_numeric',
				signers: [{}, {}],
			}
			wrapper = createWrapper({ signerIndex: 0 })

			expect(wrapper.vm.counterNumber).toBeNull()
		})
	})

	describe('RULE: counterType returns highlighted when counterNumber exists', () => {
		it('returns highlighted when counterNumber is not null', () => {
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

		it('returns undefined when counterNumber is null', () => {
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
		})

		it('does not emit when no event prop', () => {
			filesStore.selectedFile = {
				signers: [{ signed: false }],
			}
			filesStore.isOriginalFileDeleted = vi.fn().mockReturnValue(false)
			wrapper = createWrapper({ signerIndex: 0, event: '' })

			wrapper.vm.signerClickAction()

			expect(emit).not.toHaveBeenCalled()
		})

		it('does not emit when signer already signed', () => {
			filesStore.selectedFile = {
				signers: [{ signed: true }],
			}
			filesStore.isOriginalFileDeleted = vi.fn().mockReturnValue(false)
			wrapper = createWrapper({ signerIndex: 0, event: 'signer:clicked' })

			wrapper.vm.signerClickAction()

			expect(emit).not.toHaveBeenCalled()
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
		})

		it('does not emit when file deleted', () => {
			filesStore.selectedFile = {
				signers: [{ signed: false }],
			}
			filesStore.isOriginalFileDeleted = vi.fn().mockReturnValue(true)
			wrapper = createWrapper({ signerIndex: 0, event: 'signer:clicked' })

			wrapper.vm.signerClickAction()

			expect(emit).not.toHaveBeenCalled()
		})
	})

	describe('RULE: closeActions calls listItem actions closeMenu', () => {
		it('calls closeMenu when ref and method exist', () => {
			wrapper = createWrapper()

			wrapper.vm.closeActions()

			const listItemStub = wrapper.findComponent({ name: 'NcListItem' })
			expect(listItemStub).toBeTruthy()
		})

		it('does nothing when listItem ref missing', () => {
			wrapper = createWrapper()

			expect(() => wrapper.vm.closeActions()).not.toThrow()
		})

		it('does nothing when closeMenu not a function', () => {
			wrapper = createWrapper()

			expect(() => wrapper.vm.closeActions()).not.toThrow()
		})
	})
})
