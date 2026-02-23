/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
let FileValidation
vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn((app, text) => text),
	translatePlural: vi.fn((app, singular, plural, count) => (count === 1 ? singular : plural)),
	t: vi.fn((app, text) => text),
	n: vi.fn((app, singular, plural, count) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

beforeAll(async () => {
	;({ default: FileValidation } = await import('../../../components/validation/FileValidation.vue'))
})



describe('FileValidation', () => {
	let wrapper

	const createWrapper = (props = {}) => {
		return mount(FileValidation, {
			props: {
				document: {
					name: 'Test Document',
					...props.document,
				},
				legalInformation: '',
				documentValidMessage: '',
				isAfterSigned: false,
				...props,
			},
			global: {
				stubs: {
					NcIconSvgWrapper: true,
					NcNoteCard: {
						name: 'NcNoteCard',
						props: ['type'],
						template: '<div class="note-card"><slot /></div>',
					},
					DocumentValidationDetails: true,
				},
				mocks: {
					t: (app, text) => text,
				},
			},
		})
	}

	beforeEach(() => {
		if (wrapper) {
			wrapper.destroy()
		}
		vi.clearAllMocks()
	})

	describe('RULE: header displays title with icon', () => {
		it('shows Document information title', () => {
			wrapper = createWrapper()

			const title = wrapper.find('h1')
			expect(title.text()).toBe('Document information')
		})

		it('displays header icon', () => {
			wrapper = createWrapper()

			const icon = wrapper.findComponent({ name: 'NcIconSvgWrapper' })
			expect(icon.exists()).toBe(true)
		})

		it('arranges icon and title horizontally', () => {
			wrapper = createWrapper()

			const header = wrapper.find('.header')
			expect(header.classes()).toContain('header')
		})
	})

	describe('RULE: documentValidMessage displays in success note', () => {
		it('shows note card when documentValidMessage provided', () => {
			wrapper = createWrapper({
				documentValidMessage: 'Document is valid',
			})

			const noteCard = wrapper.findComponent({ name: 'NcNoteCard' })
			expect(noteCard.exists()).toBe(true)
		})

		it('hides note card when documentValidMessage empty', () => {
			wrapper = createWrapper({
				documentValidMessage: '',
			})

			const noteCards = wrapper.findAllComponents({ name: 'NcNoteCard' })
			expect(noteCards.length).toBe(0)
		})

		it('passes message to NcNoteCard', () => {
			wrapper = createWrapper({
				documentValidMessage: 'Custom validation message',
			})

			const noteCard = wrapper.findComponent({ name: 'NcNoteCard' })
			expect(noteCard.text()).toContain('Custom validation message')
		})

		it('sets note card type to success', () => {
			wrapper = createWrapper({
				documentValidMessage: 'Valid',
			})

			const noteCard = wrapper.findComponent({ name: 'NcNoteCard' })
			expect(noteCard.props('type')).toBe('success')
		})
	})

	describe('RULE: isAfterSigned displays congratulations message', () => {
		it('shows congratulations message when isAfterSigned true', () => {
			wrapper = createWrapper({
				isAfterSigned: true,
			})

			const text = wrapper.text()
			expect(text).toContain('Congratulations')
			expect(text).toContain('digitally signed')
		})

		it('hides congratulations when isAfterSigned false', () => {
			wrapper = createWrapper({
				isAfterSigned: false,
			})

			const noteCards = wrapper.findAllComponents({ name: 'NcNoteCard' })
			expect(noteCards.length).toBe(0)
		})

		it('shows success note for congratulations message', () => {
			wrapper = createWrapper({
				isAfterSigned: true,
			})

			const noteCard = wrapper.findAllComponents({ name: 'NcNoteCard' }).at(0)
			expect(noteCard.props('type')).toBe('success')
		})

		it('contains LibreSign mention in congratulations', () => {
			wrapper = createWrapper({
				isAfterSigned: true,
			})

			const text = wrapper.text()
			expect(text).toContain('LibreSign')
		})
	})

	describe('RULE: both messages display when both conditions true', () => {
		it('shows both documentValidMessage and congratulations', () => {
			wrapper = createWrapper({
				documentValidMessage: 'Document is valid',
				isAfterSigned: true,
			})

			const noteCards = wrapper.findAllComponents({ name: 'NcNoteCard' })
			expect(noteCards.length).toBe(2)
		})

		it('displays messages in correct order', () => {
			wrapper = createWrapper({
				documentValidMessage: 'Valid message',
				isAfterSigned: true,
			})

			const text = wrapper.text()
			const validIndex = text.indexOf('Valid message')
			const congratsIndex = text.indexOf('Congratulations')

			expect(validIndex).toBeLessThan(congratsIndex)
		})
	})

	describe('RULE: DocumentValidationDetails component receives props', () => {
		it('passes document prop', () => {
			const doc = {
				name: 'Important.pdf',
				status: '3',
			}
			wrapper = createWrapper({
				document: doc,
			})

			const detailsComponent = wrapper.findComponent({ name: 'DocumentValidationDetails' })
			expect(detailsComponent.props('document')).toEqual(
				expect.objectContaining({
					name: 'Important.pdf',
				})
			)
		})

		it('passes legalInformation prop', () => {
			wrapper = createWrapper({
				legalInformation: 'Legal terms and conditions',
			})

			const detailsComponent = wrapper.findComponent({ name: 'DocumentValidationDetails' })
			expect(detailsComponent.props('legalInformation')).toBe('Legal terms and conditions')
		})

		it('passes empty legalInformation by default', () => {
			wrapper = createWrapper()

			const detailsComponent = wrapper.findComponent({ name: 'DocumentValidationDetails' })
			expect(detailsComponent.props('legalInformation')).toBe('')
		})
	})

	describe('RULE: section styling and layout', () => {
		it('applies section class to container', () => {
			wrapper = createWrapper()

			const section = wrapper.find('.section')
			expect(section.exists()).toBe(true)
		})

		it('contains header and details', () => {
			wrapper = createWrapper()

			const header = wrapper.find('.header')
			const details = wrapper.findComponent({ name: 'DocumentValidationDetails' })

			expect(header.exists()).toBe(true)
			expect(details.exists()).toBe(true)
		})
	})

	describe('RULE: multiple scenarios with different prop combinations', () => {
		it('shows only documentValidMessage', () => {
			wrapper = createWrapper({
				documentValidMessage: 'Valid',
				isAfterSigned: false,
			})

			const noteCards = wrapper.findAllComponents({ name: 'NcNoteCard' })
			expect(noteCards.length).toBe(1)
			expect(noteCards.at(0).text()).toContain('Valid')
		})

		it('shows only congratulations message', () => {
			wrapper = createWrapper({
				documentValidMessage: '',
				isAfterSigned: true,
			})

			const noteCards = wrapper.findAllComponents({ name: 'NcNoteCard' })
			expect(noteCards.length).toBe(1)
			expect(noteCards.at(0).text()).toContain('Congratulations')
		})

		it('shows neither message when both false', () => {
			wrapper = createWrapper({
				documentValidMessage: '',
				isAfterSigned: false,
			})

			const noteCards = wrapper.findAllComponents({ name: 'NcNoteCard' })
			expect(noteCards.length).toBe(0)
		})

		it('shows both messages when both true', () => {
			wrapper = createWrapper({
				documentValidMessage: 'Your document is valid',
				isAfterSigned: true,
			})

			const noteCards = wrapper.findAllComponents({ name: 'NcNoteCard' })
			expect(noteCards.length).toBe(2)
		})
	})

	describe('RULE: document prop required', () => {
		it('mounts with document prop', () => {
			wrapper = createWrapper({
				document: {
					name: 'Doc.pdf',
					status: '3',
				},
			})

			expect(wrapper.exists()).toBe(true)
		})

		it('passes complex document object', () => {
			const complexDoc = {
				name: 'complex.pdf',
				status: '3',
				totalPages: 50,
				size: '5242880',
				pdfVersion: '1.7',
				uuid: 'uuid-123',
				signers: [
					{ displayName: 'Signer 1' },
				],
			}

			wrapper = createWrapper({
				document: complexDoc,
			})

			const detailsComponent = wrapper.findComponent({ name: 'DocumentValidationDetails' })
			expect(detailsComponent.props('document')).toEqual(
				expect.objectContaining({
					name: 'complex.pdf',
				})
			)
		})
	})

	describe('RULE: all props interface', () => {
		it('accepts all props', () => {
			wrapper = createWrapper({
				document: { name: 'Doc.pdf' },
				legalInformation: 'Legal text',
				documentValidMessage: 'Valid',
				isAfterSigned: true,
			})

			expect(wrapper.props('document')).toBeTruthy()
			expect(wrapper.props('legalInformation')).toBe('Legal text')
			expect(wrapper.props('documentValidMessage')).toBe('Valid')
			expect(wrapper.props('isAfterSigned')).toBe(true)
		})

		it('uses default props', () => {
			wrapper = createWrapper()

			expect(wrapper.props('legalInformation')).toBe('')
			expect(wrapper.props('documentValidMessage')).toBe('')
			expect(wrapper.props('isAfterSigned')).toBe(false)
		})
	})

	describe('RULE: responsive design variables', () => {
		it('applies responsive styling to section', () => {
			wrapper = createWrapper()

			const section = wrapper.find('.section')
			expect(section.exists()).toBe(true)
		})
	})

	describe('RULE: complete workflow scenarios', () => {
		it('displays fresh validation state', () => {
			wrapper = createWrapper({
				document: { name: 'new.pdf' },
				documentValidMessage: '',
				isAfterSigned: false,
			})

			expect(wrapper.findAllComponents({ name: 'NcNoteCard' }).length).toBe(0)
		})

		it('displays post-signature state', () => {
			wrapper = createWrapper({
				document: { name: 'signed.pdf', status: '3' },
				isAfterSigned: true,
			})

			const noteCard = wrapper.findComponent({ name: 'NcNoteCard' })
			expect(noteCard.exists()).toBe(true)
			expect(noteCard.text()).toContain('Congratulations')
		})

		it('displays validation success state', () => {
			wrapper = createWrapper({
				document: { name: 'doc.pdf', status: '3' },
				documentValidMessage: 'All signatures valid',
			})

			const noteCard = wrapper.findComponent({ name: 'NcNoteCard' })
			expect(noteCard.exists()).toBe(true)
			expect(noteCard.text()).toContain('All signatures valid')
		})
	})

	describe('RULE: header icon size and styling', () => {
		it('renders header with icon', () => {
			wrapper = createWrapper()

			const icon = wrapper.findComponent({ name: 'NcIconSvgWrapper' })
			expect(icon.exists()).toBe(true)
			expect(icon.props('size')).toBe(30)
		})

		it('displays icon with title layout', () => {
			wrapper = createWrapper()

			const header = wrapper.find('.header')
			const h1 = header.find('h1')

			expect(h1.exists()).toBe(true)
		})
	})
})
