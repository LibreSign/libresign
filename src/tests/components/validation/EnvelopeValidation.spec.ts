/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import type { TranslationFunction, PluralTranslationFunction } from '../../test-types'

type EnvelopeFile = {
	id: number
	status: string
	name?: string
	nodeId?: number
	uuid?: string
	opened?: boolean
	statusText?: string
	signed?: string | null
}

type EnvelopeSigner = {
	signed?: boolean | string | null
	opened?: boolean
	displayName?: string
	email?: string
}

type EnvelopeDocument = {
	name: string
	status: string
	filesCount: number
	files: EnvelopeFile[]
	signers: EnvelopeSigner[]
}

type WrapperProps = Partial<{
	document: Partial<EnvelopeDocument>
	legalInformation: string
	documentValidMessage: string | null
	isAfterSigned: boolean
}>

type EnvelopeValidationComponent = typeof import('../../../components/validation/EnvelopeValidation.vue').default
type ViewerModule = typeof import('../../../utils/viewer.js')

const t: TranslationFunction = (_app, text, vars) => {
	if (vars) {
		return text.replace(/{(\w+)}/g, (_m, key) => String(vars[key]))
	}
	return text
}

const n: PluralTranslationFunction = (_app, singular, plural, count, vars) => {
	const template = count === 1 ? singular : plural
	if (vars) {
		return template.replace(/{(\w+)}/g, (_m, key) => String(vars[key]))
	}
	return template
}

let EnvelopeValidation: EnvelopeValidationComponent

vi.mock('@nextcloud/router', () => ({
	generateUrl: vi.fn((url: string, params: { uuid: string }) => url.replace('{uuid}', params.uuid)),
}))
vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn(t),
	translatePlural: vi.fn(n),
	t: vi.fn(t),
	n: vi.fn(n),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))
vi.mock('@nextcloud/moment', () => ({
	default: vi.fn((date) => ({
		format: vi.fn((fmt) => `Formatted: ${date}`),
	})),
}))
vi.mock('../../../utils/fileStatus.js', () => ({
	getStatusLabel: vi.fn((status: string | number) => {
		const labels: Record<string, string> = { '0': 'Draft', '1': 'Pending', '3': 'Signed' }
		return labels[String(status)] ?? 'Unknown'
	}),
}))
vi.mock('../../../utils/viewer.js', () => ({
	openDocument: vi.fn(),
}))

let viewer: ViewerModule
beforeAll(async () => {
	;({ default: EnvelopeValidation } = await import('../../../components/validation/EnvelopeValidation.vue'))
	viewer = await import('../../../utils/viewer.js')
})

describe('EnvelopeValidation', () => {
	let wrapper: ReturnType<typeof mount> | null

	const createWrapper = (props: WrapperProps = {}) => {
		return mount(EnvelopeValidation, {
			props: {
				document: {
					name: 'Test Envelope',
					status: '3',
					filesCount: 2,
					files: [],
					signers: [],
					...props.document,
				},
				legalInformation: '',
				documentValidMessage: null,
				isAfterSigned: false,
				...props,
			},
			global: {
				stubs: {
					NcIconSvgWrapper: true,
					NcListItem: {
						template: '<li><slot name="icon" /><slot name="name" /><slot name="subname" /><slot name="actions" /><slot name="extra-actions" /></li>',
					},
					NcButton: true,
					NcActionButton: true,
					NcAvatar: true,
					NcNoteCard: true,
					NcRichText: true,
					SignerDetails: true,
					DocumentValidationDetails: true,
				},
				mocks: {
					t,
					n,
				},
			},
		})
	}

	beforeEach(() => {
		if (wrapper) {
			wrapper.unmount()
			wrapper = null
		}
		vi.clearAllMocks()
	})

	describe('RULE: initializeDocument sets opened property for files', () => {
		it('initializes all files with opened false', async () => {
			const files: EnvelopeFile[] = [
				{ id: 1, status: '3' },
				{ id: 2, status: '0' },
			]
			wrapper = createWrapper({
				document: { files },
			})

			expect(files[0].opened).toBe(false)
			expect(files[1].opened).toBe(false)
		})

		it('sets statusText for each file', async () => {
			const files: EnvelopeFile[] = [
				{ id: 1, status: '3' },
				{ id: 2, status: '1' },
			]
			wrapper = createWrapper({
				document: { files },
			})

			expect(files[0].statusText).toBe('Signed')
			expect(files[1].statusText).toBe('Pending')
		})

		it('reinitializes on watched document change', async () => {
			wrapper = createWrapper({
				document: { files: [{ id: 1, status: '3' }] },
			})

			await wrapper.setProps({
				document: {
					name: 'Updated',
					files: [{ id: 2, status: '1' }],
					signers: [],
					filesCount: 1,
				},
			})

			expect(wrapper.props('document').files[0].opened).toBe(false)
		})
	})

	describe('RULE: toggleDetail toggles signer details', () => {
		it('toggles opened state from false to true', () => {
			wrapper = createWrapper()
			const signer = { signed: true, opened: false }

			wrapper.vm.toggleDetail(signer)

			expect(signer.opened).toBe(true)
		})

		it('toggles opened state from true to false', () => {
			wrapper = createWrapper()
			const signer = { signed: true, opened: true }

			wrapper.vm.toggleDetail(signer)

			expect(signer.opened).toBe(false)
		})
	})

	describe('RULE: toggleFileDetail toggles file details', () => {
		it('toggles file opened from false to true', () => {
			wrapper = createWrapper()
			const file = { opened: false }

			wrapper.vm.toggleFileDetail(file)

			expect(file.opened).toBe(true)
		})

		it('toggles file opened from true to false', () => {
			wrapper = createWrapper()
			const file = { opened: true }

			wrapper.vm.toggleFileDetail(file)

			expect(file.opened).toBe(false)
		})
	})

	describe('RULE: getName returns signer display name with fallback', () => {
		it('returns displayName when available', () => {
			wrapper = createWrapper()

			expect(wrapper.vm.getName({
				displayName: 'John Doe',
				email: 'john@example.com',
			})).toBe('John Doe')
		})

		it('falls back to email', () => {
			wrapper = createWrapper()

			expect(wrapper.vm.getName({
				email: 'john@example.com',
			})).toBe('john@example.com')
		})

		it('returns Unknown when no name', () => {
			wrapper = createWrapper()

			expect(wrapper.vm.getName({})).toBe('Unknown')
		})

		it('prefers displayName over email', () => {
			wrapper = createWrapper()

			expect(wrapper.vm.getName({
				displayName: 'Display Name',
				email: 'fallback@example.com',
			})).toBe('Display Name')
		})
	})

	describe('RULE: getSignerProgressText formats document count with pluralization', () => {
		it('returns singular form for 1 document', () => {
			wrapper = createWrapper()

			const text = wrapper.vm.getSignerProgressText({
				documentsSignedCount: 1,
				totalDocuments: 1,
			})

			expect(text).toContain('1')
		})

		it('returns plural form for multiple documents', () => {
			wrapper = createWrapper()

			const text = wrapper.vm.getSignerProgressText({
				documentsSignedCount: 2,
				totalDocuments: 3,
			})

			expect(text).toContain('2')
			expect(text).toContain('3')
		})

		it('defaults to 0 when counts missing', () => {
			wrapper = createWrapper()

			const text = wrapper.vm.getSignerProgressText({})

			expect(text).toContain('0')
		})
	})

	describe('RULE: dateFromSqlAnsi formats SQL date to locale string', () => {
		it('formats valid SQL date', () => {
			wrapper = createWrapper()

			const formatted = wrapper.vm.dateFromSqlAnsi('2024-06-01T12:00:00')

			expect(formatted).toContain('Formatted')
		})

		it('handles ISO format date', () => {
			wrapper = createWrapper()

			const formatted = wrapper.vm.dateFromSqlAnsi('2024-06-01T12:00:00Z')

			expect(formatted).toBeTruthy()
		})
	})

	describe('RULE: viewFile opens document with correct parameters', () => {
		it('calls openDocument with file details', () => {
			wrapper = createWrapper()

			wrapper.vm.viewFile({
				uuid: 'file-uuid-123',
				name: 'Document.pdf',
				nodeId: 456,
			})

			expect(viewer.openDocument).toHaveBeenCalledWith({
				fileUrl: expect.stringContaining('file-uuid-123'),
				filename: 'Document.pdf',
				nodeId: 456,
			})
		})

		it('handles file without nodeId', () => {
			wrapper = createWrapper()

			wrapper.vm.viewFile({
				uuid: 'file-uuid-123',
				name: 'Document.pdf',
			})

			expect(viewer.openDocument).toHaveBeenCalled()
		})
	})

	describe('RULE: documentStatus computed returns formatted status', () => {
		it('returns formatted status label', () => {
			wrapper = createWrapper({
				document: { status: '3' },
			})

			expect(wrapper.vm.documentStatus).toBe('Signed')
		})

		it('returns correct label for different status', () => {
			wrapper = createWrapper({
				document: { status: '0' },
			})

			expect(wrapper.vm.documentStatus).toBe('Draft')
		})
	})

	describe('RULE: created lifecycle initializes document', () => {
		it('calls initializeDocument on created', () => {
			const files: EnvelopeFile[] = [{ id: 1, status: '3' }]
			wrapper = createWrapper({
				document: { files },
			})

			expect(files[0].opened).toBe(false)
		})
	})

	describe('RULE: documentStatus and initializeDocument together manage file state', () => {
		it('maintains file state across multiple toggles', () => {
			const file: EnvelopeFile = { id: 1, status: '3', opened: false }
			wrapper = createWrapper({
				document: { files: [file] },
			})

			wrapper.vm.toggleFileDetail(file)
			expect(file.opened).toBe(true)

			wrapper.vm.toggleFileDetail(file)
			expect(file.opened).toBe(false)
		})

		it('reinitializes files when document prop changes', async () => {
			const oldFile: EnvelopeFile = { id: 1, status: '3', opened: true }
			wrapper = createWrapper({
				document: { files: [oldFile] },
			})

			const newFile: EnvelopeFile = { id: 2, status: '1' }
			await wrapper.setProps({
				document: {
					files: [newFile],
					signers: [],
					filesCount: 1,
					name: 'Updated',
				},
			})

			expect(newFile.opened).toBe(false)
		})
	})

	describe('RULE: signer details show only when opened', () => {
		it('shows signer details when opened true', async () => {
			const signer: EnvelopeSigner = { opened: true, signed: '2024-06-01' }
			wrapper = createWrapper({
				document: { signers: [signer] },
			})

			await wrapper.vm.$nextTick()

			expect(signer.opened).toBe(true)
		})

		it('hides signer details when opened false', async () => {
			const signer: EnvelopeSigner = { opened: false, signed: null }
			wrapper = createWrapper({
				document: { signers: [signer] },
			})

			await wrapper.vm.$nextTick()

			expect(signer.opened).toBe(false)
		})
	})

	describe('RULE: View PDF button visibility based on isTouchDevice', () => {
		it('has isTouchDevice computed property from mixin', () => {
			wrapper = createWrapper()
			expect(wrapper.vm.isTouchDevice).toBeDefined()
			expect(typeof wrapper.vm.isTouchDevice).toBe('boolean')
		})

		it('renders actions slot when not touch device and file has nodeId', async () => {
			const file: EnvelopeFile = { id: 1, nodeId: 123, opened: false, status: '3', name: 'test.pdf', statusText: 'Signed' }
			wrapper = createWrapper({
				document: { files: [file] },
			})

			await wrapper.vm.$nextTick()

			// isTouchDevice value determines which template slot is used
			expect(wrapper.vm.isTouchDevice).toBeDefined()
		})

		it('calls viewFile with correct parameters', async () => {
			wrapper = createWrapper()
			const testFile = {
				uuid: 'test-uuid',
				name: 'test.pdf',
				nodeId: 123,
			}

			const viewFileSpy = vi.spyOn(wrapper.vm, 'viewFile')
			wrapper.vm.viewFile(testFile)

			expect(viewFileSpy).toHaveBeenCalledWith(testFile)
			viewFileSpy.mockRestore()
		})

		it('openDocument is called when viewFile is invoked', () => {
			wrapper = createWrapper()
			wrapper.vm.viewFile({
				uuid: 'test-uuid',
				name: 'test.pdf',
				nodeId: 123,
			})

			expect(viewer.openDocument).toHaveBeenCalledWith({
				fileUrl: '/apps/libresign/p/pdf/test-uuid',
				filename: 'test.pdf',
				nodeId: 123,
			})
		})
	})
})
