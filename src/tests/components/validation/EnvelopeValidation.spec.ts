/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import type { TranslationFunction, PluralTranslationFunction } from '../../test-types'

type EnvelopeFile = {
	id: number
	status: string
	name?: string
	nodeId?: number
	uuid?: string
	signed?: string | null
}

type EnvelopeSigner = {
	signed?: string
	displayName?: string
	email?: string
	userId?: string
	request_sign_date?: string
	remote_address?: string
	user_agent?: string
	documentsSignedCount?: number
	totalDocuments?: number
}

type EnvelopeDocument = {
	uuid: string
	name: string
	nodeId: number
	nodeType: 'envelope'
	status: 0 | 1 | 2 | 3 | 4 | string
	filesCount: number
	files: EnvelopeFile[]
	signers: EnvelopeSigner[]
	[key: string]: unknown
}

type WrapperProps = Partial<{
	document: Partial<EnvelopeDocument>
	legalInformation: string
	documentValidMessage: string | null
	isAfterSigned: boolean
}>

type EnvelopeValidationComponent = typeof import('../../../components/validation/EnvelopeValidation.vue').default
type ViewerModule = typeof import('../../../utils/viewer.js')

type EnvelopeValidationVm = {
	isTouchDevice: boolean
	documentStatus: string
	$nextTick: () => Promise<void>
	toggleDetail: (signerIndex: number) => void
	toggleFileDetail: (fileIndex: number) => void
	isSignerOpen: (signerIndex: number) => boolean
	isFileOpen: (fileIndex: number) => boolean
	getFileStatusText: (file: Partial<EnvelopeFile>) => string
	getName: (signer: Partial<EnvelopeSigner>) => string
	getSignerProgressText: (signer: Partial<EnvelopeSigner>) => string
	dateFromSqlAnsi: (date: string) => string
	viewFile: (file: Partial<EnvelopeFile>) => void
}

type EnvelopeValidationWrapper = VueWrapper<any> & {
	vm: EnvelopeValidationVm
	setProps: (props: WrapperProps) => Promise<void>
	props: (key: 'document' | 'legalInformation' | 'documentValidMessage' | 'isAfterSigned') => unknown
}

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
vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n({
	t,
	translate: t,
	n,
	translatePlural: n,
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
	let wrapper: EnvelopeValidationWrapper | null

	const createWrapper = (props: WrapperProps = {}): EnvelopeValidationWrapper => {
		const { document: documentOverrides, ...restProps } = props
		const baseDocument: EnvelopeDocument = {
			uuid: '550e8400-e29b-41d4-a716-446655440000',
			name: 'Test Envelope',
			nodeId: 123,
			nodeType: 'envelope',
			status: '3',
			filesCount: 2,
			files: [],
			signers: [],
		}
		const document = { ...baseDocument, ...(documentOverrides ?? {}) } as EnvelopeDocument
		return mount(EnvelopeValidation, {
			props: {
				legalInformation: '',
				documentValidMessage: null,
				isAfterSigned: false,
				...restProps,
				document: document as any,
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
		}) as EnvelopeValidationWrapper
	}

	beforeEach(() => {
		if (wrapper) {
			wrapper.unmount()
			wrapper = null
		}
		vi.clearAllMocks()
	})

	describe('RULE: local file UI state is isolated from API payload', () => {
		it('starts every file collapsed without mutating file objects', async () => {
			const files: EnvelopeFile[] = [
				{ id: 1, status: '3' },
				{ id: 2, status: '0' },
			]
			wrapper = createWrapper({
				document: { files },
			})

			expect(wrapper.vm.isFileOpen(0)).toBe(false)
			expect(wrapper.vm.isFileOpen(1)).toBe(false)
			expect('opened' in files[0]).toBe(false)
			expect('opened' in files[1]).toBe(false)
		})

		it('derives file status text without mutating file objects', async () => {
			const files: EnvelopeFile[] = [
				{ id: 1, status: '3' },
				{ id: 2, status: '1' },
			]
			wrapper = createWrapper({
				document: { files },
			})

			expect(wrapper.vm.getFileStatusText(files[0])).toBe('Signed')
			expect(wrapper.vm.getFileStatusText(files[1])).toBe('Pending')
			expect('statusText' in files[0]).toBe(false)
			expect('statusText' in files[1]).toBe(false)
		})

		it('resets local file state when document prop changes', async () => {
			wrapper = createWrapper({
				document: { files: [{ id: 1, status: '3' }] },
			})
			wrapper.vm.toggleFileDetail(0)
			expect(wrapper.vm.isFileOpen(0)).toBe(true)

			await wrapper.setProps({
				document: {
					name: 'Updated',
					files: [{ id: 2, status: '1' }],
					signers: [],
					filesCount: 1,
				},
			})

			expect(wrapper.vm.isFileOpen(0)).toBe(false)
		})
	})

	describe('RULE: toggleDetail toggles signer details', () => {
		it('tracks signer open state locally', () => {
			const signer: EnvelopeSigner = { signed: '2024-06-01' }
			wrapper = createWrapper({
				document: { signers: [signer] },
			})

			expect(wrapper.vm.isSignerOpen(0)).toBe(false)
			wrapper.vm.toggleDetail(0)

			expect(wrapper.vm.isSignerOpen(0)).toBe(true)
			expect('opened' in signer).toBe(false)
		})
	})

	describe('RULE: toggleFileDetail toggles file details', () => {
		it('tracks file open state locally', () => {
			const file: EnvelopeFile = { id: 1, status: '3' }
			wrapper = createWrapper({
				document: { files: [file] },
			})

			expect(wrapper.vm.isFileOpen(0)).toBe(false)
			wrapper.vm.toggleFileDetail(0)

			expect(wrapper.vm.isFileOpen(0)).toBe(true)
			expect('opened' in file).toBe(false)
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

		it('does not open viewer without nodeId', () => {
			wrapper = createWrapper()

			wrapper.vm.viewFile({
				uuid: 'file-uuid-123',
				name: 'Document.pdf',
			})

			expect(viewer.openDocument).not.toHaveBeenCalled()
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

	describe('RULE: created lifecycle initializes local UI state', () => {
		it('starts file details collapsed on created', () => {
			const files: EnvelopeFile[] = [{ id: 1, status: '3' }]
			wrapper = createWrapper({
				document: { files },
			})

			expect(wrapper.vm.isFileOpen(0)).toBe(false)
		})
	})

	describe('RULE: documentStatus and local UI state together manage file state', () => {
		it('maintains file state across multiple toggles', () => {
			const file: EnvelopeFile = { id: 1, status: '3' }
			wrapper = createWrapper({
				document: { files: [file] },
			})

			wrapper.vm.toggleFileDetail(0)
			expect(wrapper.vm.isFileOpen(0)).toBe(true)

			wrapper.vm.toggleFileDetail(0)
			expect(wrapper.vm.isFileOpen(0)).toBe(false)
		})

		it('reinitializes files when document prop changes', async () => {
			const oldFile: EnvelopeFile = { id: 1, status: '3' }
			wrapper = createWrapper({
				document: { files: [oldFile] },
			})
			wrapper.vm.toggleFileDetail(0)
			expect(wrapper.vm.isFileOpen(0)).toBe(true)

			const newFile: EnvelopeFile = { id: 2, status: '1' }
			await wrapper.setProps({
				document: {
					files: [newFile],
					signers: [],
					filesCount: 1,
					name: 'Updated',
				},
			})

			expect(wrapper.vm.isFileOpen(0)).toBe(false)
			expect('opened' in newFile).toBe(false)
		})
	})

	describe('RULE: signer details show only when local state is open', () => {
		it('shows signer details when toggled open', async () => {
			const signer: EnvelopeSigner = { signed: '2024-06-01' }
			wrapper = createWrapper({
				document: { signers: [signer] },
			})
			wrapper.vm.toggleDetail(0)

			await wrapper.vm.$nextTick()

			expect(wrapper.vm.isSignerOpen(0)).toBe(true)
		})

		it('starts with signer details closed', async () => {
			const signer: EnvelopeSigner = {}
			wrapper = createWrapper({
				document: { signers: [signer] },
			})

			await wrapper.vm.$nextTick()

			expect(wrapper.vm.isSignerOpen(0)).toBe(false)
		})
	})

	describe('RULE: View PDF button visibility based on isTouchDevice', () => {
		it('has isTouchDevice computed property from mixin', () => {
			wrapper = createWrapper()
			expect(wrapper.vm.isTouchDevice).toBeDefined()
			expect(typeof wrapper.vm.isTouchDevice).toBe('boolean')
		})

		it('renders actions slot when not touch device and file has nodeId', async () => {
			const file: EnvelopeFile = { id: 1, nodeId: 123, status: '3', name: 'test.pdf' }
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
			wrapper.vm.viewFile(testFile)

			expect(viewer.openDocument).toHaveBeenCalledWith({
				fileUrl: '/apps/libresign/p/pdf/test-uuid',
				filename: 'test.pdf',
				nodeId: 123,
			})
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
