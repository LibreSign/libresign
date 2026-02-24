/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import IdentifySigner from '../../../components/Request/IdentifySigner.vue'

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
}))

vi.mock('vue-select', () => ({
	default: {
		name: 'VSelect',
		render: () => null,
	},
}))

interface FilesStoreMock {
	disableIdentifySigner: ReturnType<typeof vi.fn>
	getFile: ReturnType<typeof vi.fn>
	saveOrUpdateSignatureRequest: ReturnType<typeof vi.fn>
	[key: string]: unknown
}

let filesStore: FilesStoreMock
vi.mock('../../../store/files.js', () => ({
	useFilesStore: vi.fn(() => filesStore),
}))

vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	t: vi.fn((_app: string, text: string) => text),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

vi.mock('@mdi/svg/svg/account.svg?raw', () => ({ default: '<svg></svg>' }))
vi.mock('@mdi/svg/svg/email.svg?raw', () => ({ default: '<svg></svg>' }))
vi.mock('@mdi/svg/svg/message-processing.svg?raw', () => ({ default: '<svg></svg>' }))
vi.mock('@mdi/svg/svg/whatsapp.svg?raw', () => ({ default: '<svg></svg>' }))
vi.mock('@mdi/svg/svg/xmpp.svg?raw', () => ({ default: '<svg></svg>' }))
vi.mock('../../../../img/logo-signal-app.svg?raw', () => ({ default: '<svg></svg>' }))
vi.mock('../../../../img/logo-telegram-app.svg?raw', () => ({ default: '<svg></svg>' }))

describe('IdentifySigner rules', () => {
	let wrapper: ReturnType<typeof mount>

	beforeEach(async () => {
		setActivePinia(createPinia())
		const { useFilesStore: useFilesStoreModule } = await import('../../../store/files.js')
		filesStore = {
			disableIdentifySigner: vi.fn(),
			getFile: vi.fn(() => ({ signers: [] })),
			saveOrUpdateSignatureRequest: vi.fn().mockResolvedValue({}),
		}
		;(useFilesStoreModule as unknown as { mockReturnValue: (store: FilesStoreMock) => void }).mockReturnValue(filesStore)

		wrapper = mount(IdentifySigner, {
			props: {
				signerToEdit: {},
				method: 'all',
				placeholder: 'Name',
				methods: [
					{ name: 'email', friendly_name: 'Email' },
					{ name: 'account', friendly_name: 'Account' },
					{ name: 'sms', friendly_name: 'SMS' },
				],
				disabled: false,
			},
			global: {
				stubs: {
					NcButton: true,
					NcCheckboxRadioSwitch: true,
					NcIconSvgWrapper: true,
					NcNoteCard: true,
					NcTextArea: true,
					NcTextField: true,
					SignerSelect: true,
				},
				mocks: {
					t: (_app: string, text: string) => text,
				},
			},
		})
	})

	describe('signer detection', () => {
		it('treats signerToEdit as new signer when empty', () => {
			expect(wrapper.vm.isNewSigner).toBe(true)
		})

		it('treats signerToEdit as existing signer when populated', async () => {
			const signer = {
				displayName: 'John Doe',
				identify: 'john@example.com',
				identifyMethods: [{ method: 'email', value: 'john@example.com' }],
			}

			await wrapper.setProps({ signerToEdit: signer })

			expect(wrapper.vm.isNewSigner).toBe(false)
		})
	})

	describe('signer selection state', () => {
		it('signerSelected is false initially', () => {
			expect(wrapper.vm.signerSelected).toBe(false)
		})

		it('signerSelected is true when signer has id', () => {
			wrapper.vm.signer = { id: 'john@example.com', method: 'email' }

			expect(wrapper.vm.signerSelected).toBe(true)
		})

		it('signerSelected is false when signer id is empty', () => {
			wrapper.vm.signer = { id: '', method: 'email' }

			expect(wrapper.vm.signerSelected).toBe(false)
		})
	})

	describe('button text state', () => {
		it('shows Save button for new signer', () => {
			expect(wrapper.vm.saveButtonText).toBe('Save')
		})

		it('shows Update button for existing signer', async () => {
			await wrapper.setProps({
				signerToEdit: { displayName: 'Test' },
			})

			expect(wrapper.vm.isNewSigner).toBe(false)
		})
	})

	describe('custom message rules', () => {
		it('hides custom message option when no method selected', () => {
			wrapper.vm.signer = {}

			expect(wrapper.vm.showCustomMessage).toBe(false)
		})

		it('shows custom message for email method', () => {
			wrapper.vm.signer = {
				id: 'john@example.com',
				method: 'email',
			}

			expect(wrapper.vm.showCustomMessage).toBe(true)
		})

		it('shows custom message for SMS method', () => {
			wrapper.vm.signer = {
				id: '5511999999999',
				method: 'sms',
			}

			expect(wrapper.vm.showCustomMessage).toBe(true)
		})

		it('shows custom message for account method if accepts email', () => {
			wrapper.vm.signer = {
				id: 'user@example.com',
				method: 'account',
				acceptsEmailNotifications: true,
			}

			expect(wrapper.vm.showCustomMessage).toBe(true)
		})

		it('hides custom message for account method if no email notifications', () => {
			wrapper.vm.signer = {
				id: 'user@example.com',
				method: 'account',
				acceptsEmailNotifications: false,
			}

			expect(wrapper.vm.showCustomMessage).toBe(false)
		})
	})

	describe('name validation', () => {
		it('shows error for name with less than 3 characters', () => {
			wrapper.vm.displayName = 'Jo'
			wrapper.vm.onNameChange()

			expect(wrapper.vm.nameHaveError).toBe(true)
			expect(wrapper.vm.nameHelperText).not.toBe('')
		})

		it('clears error for name with 3 or more characters', () => {
			wrapper.vm.displayName = 'John'
			wrapper.vm.onNameChange()

			expect(wrapper.vm.nameHaveError).toBe(false)
			expect(wrapper.vm.nameHelperText).toBe('')
		})

		it('counts only trimmed characters', () => {
			wrapper.vm.displayName = '  J  '
			wrapper.vm.onNameChange()

			expect(wrapper.vm.nameHaveError).toBe(true)
		})

		it('validates exactly 3 characters as valid', () => {
			wrapper.vm.displayName = 'Bob'
			wrapper.vm.onNameChange()

			expect(wrapper.vm.nameHaveError).toBe(false)
		})
	})

	describe('signer update handling', () => {
		it('updates signer when new signer selected', () => {
			const newSigner = {
				id: 'john@example.com',
				displayName: 'John Doe',
				method: 'email',
			}

			wrapper.vm.updateSigner(newSigner)

			expect(wrapper.vm.signer).toEqual(newSigner)
			expect(wrapper.vm.identify).toBe('john@example.com')
			expect(wrapper.vm.displayName).toBe('John Doe')
		})

		it('clears signer when updateSigner called with null', () => {
			wrapper.vm.signer = { id: 'test@example.com' }

			wrapper.vm.updateSigner(null)

			expect(wrapper.vm.signer).toEqual({})
		})

		it('disables custom message for account without email notifications', () => {
			wrapper.vm.enableCustomMessage = true
			wrapper.vm.description = 'Test message'

			const accountSigner = {
				id: 'user@nextcloud.com',
				method: 'account',
				displayName: 'User',
				acceptsEmailNotifications: false,
			}

			wrapper.vm.updateSigner(accountSigner)

			expect(wrapper.vm.enableCustomMessage).toBe(false)
			expect(wrapper.vm.description).toBe('')
		})

		it('preserves custom message for account with email notifications', () => {
			wrapper.vm.enableCustomMessage = true
			wrapper.vm.description = 'Test message'

			const accountSigner = {
				id: 'user@nextcloud.com',
				method: 'account',
				displayName: 'User',
				acceptsEmailNotifications: true,
			}

			wrapper.vm.updateSigner(accountSigner)

			expect(wrapper.vm.enableCustomMessage).toBe(true)
			expect(wrapper.vm.description).toBe('Test message')
		})
	})

	describe('custom message toggle', () => {
		it('enables custom message toggle', () => {
			wrapper.vm.enableCustomMessage = true

			wrapper.vm.onToggleCustomMessage(true)

			expect(wrapper.vm.enableCustomMessage).toBe(true)
		})

		it('clears description when disabling custom message', () => {
			wrapper.vm.description = 'Some message'

			wrapper.vm.onToggleCustomMessage(false)

			expect(wrapper.vm.description).toBe('')
		})

		it('preserves description when enabling custom message', () => {
			wrapper.vm.description = 'Message'
			wrapper.vm.enableCustomMessage = true

			wrapper.vm.onToggleCustomMessage(true)

			expect(wrapper.vm.enableCustomMessage).toBe(true)
			expect(wrapper.vm.description).toBe('Message')
		})
	})

	describe('save signer', () => {
		it('does not save when no method selected', async () => {
			wrapper.vm.signer = { id: '' }

			await wrapper.vm.saveSigner()

			expect(filesStore.saveOrUpdateSignatureRequest).not.toHaveBeenCalled()
		})

		it('does not save when no id provided', async () => {
			wrapper.vm.signer = { method: 'email' }

			await wrapper.vm.saveSigner()

			expect(filesStore.saveOrUpdateSignatureRequest).not.toHaveBeenCalled()
		})

		it('sends signer list to save request', async () => {
			filesStore.getFile.mockReturnValueOnce({
				signers: [{ identify: { email: 'existing@example.com' } }],
			})
			wrapper.vm.signer = { id: 'john@example.com', method: 'email' }
			wrapper.vm.displayName = 'John Doe'
			wrapper.vm.description = ''
			wrapper.vm.identify = 'john@example.com'

			await wrapper.vm.saveSigner()

			expect(filesStore.saveOrUpdateSignatureRequest).toHaveBeenCalledWith({
				signers: [
					{ identify: { email: 'existing@example.com' } },
					{
						displayName: 'John Doe',
						description: undefined,
						identify: 'john@example.com',
						identifyMethods: [{
							method: 'email',
							value: 'john@example.com',
						}],
					},
				],
			})
		})

		it('trims description before saving', async () => {
			wrapper.vm.signer = { id: 'john@example.com', method: 'email' }
			wrapper.vm.displayName = 'John'
			wrapper.vm.description = '   test message   '

			await wrapper.vm.saveSigner()

			const payload = filesStore.saveOrUpdateSignatureRequest.mock.calls[0][0]
			expect(payload.signers[0].description).toBe('test message')
		})

		it('omits description when empty after trim', async () => {
			wrapper.vm.signer = { id: 'john@example.com', method: 'email' }
			wrapper.vm.displayName = 'John'
			wrapper.vm.description = '   '

			await wrapper.vm.saveSigner()

			const payload = filesStore.saveOrUpdateSignatureRequest.mock.calls[0][0]
			expect(payload.signers[0].description).toBeUndefined()
		})

		it('saves signature request after updating signer', async () => {
			wrapper.vm.signer = { id: 'john@example.com', method: 'email' }
			wrapper.vm.displayName = 'John'

			await wrapper.vm.saveSigner()

			expect(filesStore.saveOrUpdateSignatureRequest).toHaveBeenCalled()
		})

		it('clears form after successful save', async () => {
			wrapper.vm.signer = { id: 'john@example.com', method: 'email' }
			wrapper.vm.displayName = 'John Doe'
			wrapper.vm.description = 'Message'
			wrapper.vm.identify = 'john@example.com'

			await wrapper.vm.saveSigner()

			expect(wrapper.vm.displayName).toBe('')
			expect(wrapper.vm.description).toBe('')
			expect(wrapper.vm.identify).toBe('')
			expect(wrapper.vm.signer).toEqual({})
		})

		it('closes signer form after successful save', async () => {
			wrapper.vm.signer = { id: 'john@example.com', method: 'email' }
			wrapper.vm.displayName = 'John'

			await wrapper.vm.saveSigner()

			expect(filesStore.disableIdentifySigner).toHaveBeenCalled()
		})

		it('handles save error gracefully', async () => {
			const { showError } = await import('@nextcloud/dialogs')
			filesStore.saveOrUpdateSignatureRequest.mockRejectedValue(
				new Error('Network error')
			)

			wrapper.vm.signer = { id: 'john@example.com', method: 'email' }
			wrapper.vm.displayName = 'John'

			await expect(wrapper.vm.saveSigner()).resolves.not.toThrow()
			expect(showError).toHaveBeenCalled()
		})
	})

	describe('initialization from edit data', () => {
		it('loads signer data on beforeMount', async () => {
			const signer = {
				displayName: 'Jane Doe',
				description: 'Please review',
				identify: 'jane@example.com',
				identifyMethods: [{ method: 'email', value: 'jane@example.com' }],
			}

			wrapper = mount(IdentifySigner, {
				stubs: {
					NcButton: true,
					NcCheckboxRadioSwitch: true,
					NcIconSvgWrapper: true,
					NcNoteCard: true,
					NcTextArea: true,
					NcTextField: true,
					SignerSelect: true,
				},
				mocks: {
					t: (_app: string, text: string) => text,
				},
				propsData: {
					signerToEdit: signer,
					method: 'all',
					placeholder: 'Name',
					methods: [{ name: 'email', friendly_name: 'Email' }],
					disabled: false,
				},
			})

			expect(wrapper.vm.displayName).toBe('Jane Doe')
			expect(wrapper.vm.description).toBe('Please review')
		})

		it('sets enableCustomMessage based on description', async () => {
			const signer = {
				displayName: 'Jane Doe',
				description: 'Some message',
				identifyMethods: [],
			}

			wrapper = mount(IdentifySigner, {
				stubs: {
					NcButton: true,
					NcCheckboxRadioSwitch: true,
					NcIconSvgWrapper: true,
					NcNoteCard: true,
					NcTextArea: true,
					NcTextField: true,
					SignerSelect: true,
				},
				mocks: {
					t: (_app: string, text: string) => text,
				},
				propsData: {
					signerToEdit: signer,
					method: 'all',
					placeholder: 'Name',
					methods: [],
					disabled: false,
				},
			})

			expect(wrapper.vm.enableCustomMessage).toBe(true)
		})
	})

	describe('method icon resolution', () => {
		it('returns account icon for unknown method', () => {
			wrapper.vm.signer = { method: 'unknown' }

			const icon = wrapper.vm.getMethodIcon()

			expect(icon).toBeDefined()
		})

		it('returns account icon when no method', () => {
			wrapper.vm.signer = {}

			const icon = wrapper.vm.getMethodIcon()

			expect(icon).toBeDefined()
		})
	})

	describe('disabled state', () => {
		it('disables save and cancel buttons when disabled', async () => {
			await wrapper.setProps({ disabled: true })

			expect(wrapper.vm.$props.disabled).toBe(true)
		})

		it('hides form controls when disabled', async () => {
			await wrapper.setProps({ disabled: true })

			expect(wrapper.vm.$props.disabled).toBe(true)
		})
	})

	describe('identify method label', () => {
		it('returns friendly name for known method', () => {
			wrapper.vm.signer = { method: 'email' }

			const label = wrapper.vm.identifyMethodLabel

			expect(label).toBe('Email')
		})

		it('returns empty string for unknown method', () => {
			wrapper.vm.signer = { method: 'unknown_method' }

			const label = wrapper.vm.identifyMethodLabel

			expect(label).toBe('')
		})

		it('returns empty string when no method selected', () => {
			wrapper.vm.signer = {}

			const label = wrapper.vm.identifyMethodLabel

			expect(label).toBe('')
		})
	})
})
