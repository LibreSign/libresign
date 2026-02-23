/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
let DocumentValidationDetails

vi.mock('@nextcloud/router', () => ({
	generateUrl: vi.fn((url, params) => url.replace('{uuid}', params.uuid)),
}))
vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn((app, text) => text),
	translatePlural: vi.fn((app, singular, plural, count) => (count === 1 ? singular : plural)),
	t: vi.fn((app, text) => text),
	n: vi.fn((app, singular, plural, count) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))
vi.mock('../../../utils/fileStatus.js', () => ({
	getStatusLabel: vi.fn((status) => {
		const labels = {
			'0': 'Draft',
			'1': 'Pending',
			'3': 'Signed',
		}
		return labels[status] || 'Unknown'
	}),
}))
vi.mock('../../../utils/viewer.js', () => ({
	openDocument: vi.fn(),
}))

let fileStatus, viewer
beforeAll(async () => {
	;({ default: DocumentValidationDetails } = await import('../../../components/validation/DocumentValidationDetails.vue'))
	fileStatus = await import('../../../utils/fileStatus.js')
	viewer = await import('../../../utils/viewer.js')
})

describe('DocumentValidationDetails', () => {
	let wrapper

	const createWrapper = (props = {}) => {
		return mount(DocumentValidationDetails, {
			props: {
				document: {
					name: 'Test Document',
					...props.document,
				},
				legalInformation: '',
				documentValidMessage: null,
				isAfterSigned: false,
				...props,
			},
			global: {
				stubs: {
					NcButton: true,
					NcIconSvgWrapper: true,
					NcListItem: { template: '<li><slot name="name" /></li>' },
					NcRichText: true,
					SignerDetails: true,
				},
				mocks: {
					t: (app, text) => text,
					n: (app, sing, plur, count) => count === 1 ? sing : plur,
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

	describe('RULE: document name always displays', () => {
		it('shows document name', () => {
			wrapper = createWrapper({
				document: {
					name: 'My Important Document.pdf',
				},
			})

			const listing = wrapper.findAll('ul').at(0).text()
			expect(listing).toContain('My Important Document.pdf')
		})

		it('displays name label', () => {
			wrapper = createWrapper({
				document: {
					name: 'Test.pdf',
				},
			})

			const listing = wrapper.findAll('ul').at(0).text()
			expect(listing).toContain('Name:')
		})
	})

	describe('RULE: status displays when document has status', () => {
		it('shows status when present', () => {
			wrapper = createWrapper({
				document: {
					status: '3',
				},
			})

			const status = wrapper.vm.documentStatus
			expect(status).toBe('Signed')
		})

		it('hides status when not present', () => {
			wrapper = createWrapper({
				document: {},
			})

			expect(wrapper.vm.documentStatus).toBeTruthy()
		})

		it('displays status label from utility', () => {
			wrapper = createWrapper({
				document: {
					status: '0',
				},
			})

			expect(wrapper.vm.documentStatus).toBe('Draft')
			expect(fileStatus.getStatusLabel).toHaveBeenCalledWith('0')
		})

		it('handles different status values', () => {
			wrapper = createWrapper({
				document: {
					status: '1',
				},
			})

			expect(wrapper.vm.documentStatus).toBe('Pending')
		})
	})

	describe('RULE: total pages displays when available', () => {
		it('shows total pages when present', () => {
			wrapper = createWrapper({
				document: {
					totalPages: 42,
				},
			})

			const listing = wrapper.findAll('ul').at(0).text()
			expect(listing).toContain('Total pages:')
			expect(listing).toContain('42')
		})

		it('hides total pages when missing', () => {
			wrapper = createWrapper({
				document: {},
			})

			expect(wrapper.vm.$el.textContent).not.toContain('Total pages:')
		})

		it('displays large page count', () => {
			wrapper = createWrapper({
				document: {
					totalPages: 999,
				},
			})

			const listing = wrapper.findAll('ul').at(0).text()
			expect(listing).toContain('999')
		})
	})

	describe('RULE: file size computes in appropriate units', () => {
		it('displays size in B for bytes', () => {
			wrapper = createWrapper({
				document: {
					size: '512',
				},
			})

			expect(wrapper.vm.size).toBe('512 B')
		})

		it('converts to KB for larger files', () => {
			wrapper = createWrapper({
				document: {
					size: '2048',
				},
			})

			expect(wrapper.vm.size).toContain('KB')
		})

		it('converts to MB for large files', () => {
			wrapper = createWrapper({
				document: {
					size: '5242880',
				},
			})

			expect(wrapper.vm.size).toContain('MB')
		})

		it('rounds KB to 2 decimals', () => {
			wrapper = createWrapper({
				document: {
					size: '1536',
				},
			})

			const size = wrapper.vm.size
			expect(size).toMatch(/\d+\.\d{2} KB/)
		})

		it('rounds MB to 2 decimals', () => {
			wrapper = createWrapper({
				document: {
					size: '5242880',
				},
			})

			const size = wrapper.vm.size
			expect(size).toMatch(/\d+\.\d{2} MB/)
		})

		it('returns empty string when size missing', () => {
			wrapper = createWrapper({
				document: {},
			})

			expect(wrapper.vm.size).toBe('')
		})

		it('shows file size label when present', () => {
			wrapper = createWrapper({
				document: {
					size: '1024',
				},
			})

			const listing = wrapper.findAll('ul').at(0).text()
			expect(listing).toContain('File size:')
		})

		it('hides file size when missing', () => {
			wrapper = createWrapper({
				document: {},
			})

			expect(wrapper.vm.$el.textContent).not.toContain('File size:')
		})
	})

	describe('RULE: PDF version displays when available', () => {
		it('shows PDF version when present', () => {
			wrapper = createWrapper({
				document: {
					pdfVersion: '1.7',
				},
			})

			const listing = wrapper.findAll('ul').at(0).text()
			expect(listing).toContain('PDF version:')
			expect(listing).toContain('1.7')
		})

		it('hides PDF version when missing', () => {
			wrapper = createWrapper({
				document: {},
			})

			expect(wrapper.vm.$el.textContent).not.toContain('PDF version:')
		})

		it('displays different PDF versions', () => {
			wrapper = createWrapper({
				document: {
					pdfVersion: '2.0',
				},
			})

			const listing = wrapper.findAll('ul').at(0).text()
			expect(listing).toContain('2.0')
		})
	})

	describe('RULE: legal information displays as RichText when provided', () => {
		it('shows legal information when provided', () => {
			wrapper = createWrapper({
				legalInformation: 'This is legal information',
			})

			const infoDiv = wrapper.find('.info-document')
			expect(infoDiv.exists()).toBe(true)
		})

		it('hides legal information when empty', () => {
			wrapper = createWrapper({
				legalInformation: '',
			})

			const richText = wrapper.findComponent({ name: 'NcRichText' })
			expect(richText.exists()).toBe(false)
		})

		it('passes legal information to RichText component', () => {
			wrapper = createWrapper({
				legalInformation: 'Legal text with **markdown**',
			})

			expect(wrapper.props('legalInformation')).toBe('Legal text with **markdown**')
		})
	})

	describe('RULE: view document button opens document', () => {
		it('shows view document button when UUID present', () => {
			wrapper = createWrapper({
				document: {
					uuid: 'doc-uuid-123',
					name: 'Document.pdf',
				},
			})

			const button = wrapper.findComponent({ name: 'NcButton' })
			expect(button.exists()).toBe(true)
		})

		it('hides view document button when UUID missing', () => {
			wrapper = createWrapper({
				document: {
					name: 'Document.pdf',
				},
			})

			expect(wrapper.vm.document.uuid).toBeUndefined()
		})

		it('calls openDocument with correct parameters', async () => {
			wrapper = createWrapper({
				document: {
					uuid: 'doc-uuid-123',
					name: 'Test.pdf',
					nodeId: 456,
				},
			})

			await wrapper.vm.viewDocument()

			expect(viewer.openDocument).toHaveBeenCalledWith({
				fileUrl: expect.stringContaining('doc-uuid-123'),
				filename: 'Test.pdf',
				nodeId: 456,
			})
		})

		it('generates correct file URL', async () => {
			wrapper = createWrapper({
				document: {
					uuid: 'abc123',
					name: 'file.pdf',
				},
			})

			await wrapper.vm.viewDocument()

			expect(viewer.openDocument).toHaveBeenCalled()
		})

		it('passes document name to viewer', async () => {
			wrapper = createWrapper({
				document: {
					uuid: 'id',
					name: 'Important Document.pdf',
				},
			})

			await wrapper.vm.viewDocument()

			expect(viewer.openDocument).toHaveBeenCalledWith(
				expect.objectContaining({
					filename: 'Important Document.pdf',
				})
			)
		})
	})

	describe('RULE: signers list displays when available', () => {
		it('shows signers list when signers array present', () => {
			wrapper = createWrapper({
				document: {
					signers: [
						{ displayName: 'John Doe' },
						{ displayName: 'Jane Smith' },
					],
				},
			})

			const lists = wrapper.findAll('ul')
			expect(lists.length).toBeGreaterThan(1)
		})

		it('renders SignerDetails for each signer', () => {
			wrapper = createWrapper({
				document: {
					signers: [
						{ displayName: 'Signer 1' },
						{ displayName: 'Signer 2' },
					],
				},
			})

			const signerComponents = wrapper.findAllComponents({ name: 'SignerDetails' })
			expect(signerComponents.length).toBe(2)
		})

		it('hides signers list when empty', () => {
			wrapper = createWrapper({
				document: {
					signers: [],
				},
			})

			const signersList = wrapper.find('.signers')
			expect(signersList.exists()).toBe(false)
		})

		it('hides signers list when not present', () => {
			wrapper = createWrapper({
				document: {},
			})

			const signersList = wrapper.find('.signers')
			expect(signersList.exists()).toBe(false)
		})

		it('passes signer data to SignerDetails component', () => {
			wrapper = createWrapper({
				document: {
					signers: [
						{ displayName: 'John Doe', email: 'john@example.com' },
					],
				},
			})

			const signerComponent = wrapper.findComponent({ name: 'SignerDetails' })
			expect(signerComponent.props('signer')).toEqual(
				expect.objectContaining({
					displayName: 'John Doe',
				})
			)
		})
	})

	describe('RULE: complete document with all fields', () => {
		it('displays all information when fully populated', () => {
			wrapper = createWrapper({
				document: {
					name: 'Complete Document.pdf',
					status: '3',
					totalPages: 25,
					size: '2048576',
					pdfVersion: '1.7',
					uuid: 'uuid-123',
					nodeId: 789,
					signers: [
						{ displayName: 'Signer 1' },
					],
				},
				legalInformation: 'Legal terms here',
			})

			const text = wrapper.text()
			expect(text).toContain('Complete Document.pdf')
			expect(text).toContain('Signed')
			expect(text).toContain('25')
			expect(text).toContain('MB')
			expect(text).toContain('1.7')
		})

		it('handles minimal document data', () => {
			wrapper = createWrapper({
				document: {
					name: 'Minimal.pdf',
				},
			})

			expect(wrapper.findAll('ul').at(0).text()).toContain('Minimal.pdf')
		})
	})

	describe('RULE: document status computed property uses utility', () => {
		it('returns formatted status', () => {
			wrapper = createWrapper({
				document: {
					status: '3',
				},
			})

			expect(wrapper.vm.documentStatus).toBe('Signed')
		})

		it('calls getStatusLabel with correct status', () => {
			wrapper = createWrapper({
				document: {
					status: '1',
				},
			})

			wrapper.vm.documentStatus

			expect(fileStatus.getStatusLabel).toHaveBeenCalled()
		})
	})

	describe('RULE: info-document section contains button and legal text', () => {
		it('displays info-document section when needed', () => {
			wrapper = createWrapper({
				document: {
					uuid: 'id',
				},
				legalInformation: 'Legal info',
			})

			const infoDiv = wrapper.find('.info-document')
			expect(infoDiv.exists()).toBe(true)
		})

		it('shows button in info-document', () => {
			wrapper = createWrapper({
				document: {
					uuid: 'uuid-123',
					name: 'Doc.pdf',
				},
			})

			const infoDiv = wrapper.find('.info-document')
			expect(infoDiv.exists()).toBe(true)
		})
	})

	describe('RULE: isAfterSigned prop received', () => {
		it('accepts isAfterSigned prop', () => {
			wrapper = createWrapper({
				isAfterSigned: true,
			})

			expect(wrapper.props('isAfterSigned')).toBe(true)
		})

		it('defaults to false', () => {
			wrapper = createWrapper()

			expect(wrapper.props('isAfterSigned')).toBe(false)
		})
	})

	describe('RULE: documentValidMessage prop received', () => {
		it('accepts documentValidMessage prop', () => {
			wrapper = createWrapper({
				documentValidMessage: 'Document is valid',
			})

			expect(wrapper.props('documentValidMessage')).toBe('Document is valid')
		})

		it('defaults to null', () => {
			wrapper = createWrapper()

			expect(wrapper.props('documentValidMessage')).toBeNull()
		})
	})
})
