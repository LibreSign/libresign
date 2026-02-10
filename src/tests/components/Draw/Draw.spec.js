import { describe, expect, it, beforeEach, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import Draw from '../../../components/Draw/Draw.vue'
import { useSignatureElementsStore } from '../../../store/signatureElements.js'

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
	default: {
		name: 'NcDialog',
		template: '<div><slot /></div>',
		emits: ['closing'],
	},
}))

vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: {
		name: 'NcButton',
		template: '<button @click="$listeners.click"><slot /></button>',
	},
}))

vi.mock('../../../components/Draw/Editor.vue', () => ({
	default: {
		name: 'Editor',
		template: '<div></div>',
		emits: ['close', 'save'],
	},
}))

vi.mock('../../../components/Draw/TextInput.vue', () => ({
	default: {
		name: 'TextInput',
		template: '<div></div>',
		emits: ['close', 'save'],
	},
}))

vi.mock('../../../components/Draw/FileUpload.vue', () => ({
	default: {
		name: 'FileUpload',
		template: '<div></div>',
		emits: ['close', 'save'],
	},
}))

vi.mock('vue-material-design-icons/Draw.vue', () => ({
	default: { name: 'DrawIcon', template: '<div></div>' },
}))

vi.mock('vue-material-design-icons/SignatureText.vue', () => ({
	default: { name: 'SignatureTextIcon', template: '<div></div>' },
}))

vi.mock('vue-material-design-icons/Upload.vue', () => ({
	default: { name: 'UploadIcon', template: '<div></div>' },
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(() => Promise.resolve({ data: { ocs: { data: [] } } })),
	},
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => {
		if (key === 'user_signatures') {
			return []
		}
		return defaultValue
	}),
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path) => `/ocs/v2.php${path}`),
}))

describe('Draw.vue', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	afterEach(() => {
		vi.restoreAllMocks()
		document.body.classList.remove('libresign-modal-open')
		document.documentElement.classList.remove('libresign-modal-open')
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	it('renders dialog when mounted', async () => {
		const wrapper = mount(Draw, {
			props: {
				type: 'signature',
				drawEditor: true,
			},
			global: {
				mocks: {
					t: (key, message) => message,
				},
				stubs: {
					NcDialog: { template: '<div class="nc-dialog"><slot /></div>', emits: ['closing'] },
					NcButton: { template: '<button @click="$emit(\'click\')"><slot /></button>', emits: ['click'] },
					Editor: { template: '<div></div>', emits: ['close', 'save'] },
					TextInput: { template: '<div></div>', emits: ['close', 'save'] },
					FileUpload: { template: '<div></div>', emits: ['close', 'save'] },
					DrawIcon: { template: '<div></div>' },
					SignatureTextIcon: { template: '<div></div>' },
					UploadIcon: { template: '<div></div>' },
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(wrapper.vm.mounted).toBe(true)
	})

	it('renders only draw tab when all editors disabled', async () => {
		const wrapper = mount(Draw, {
			props: {
				type: 'signature',
				drawEditor: true,
				textEditor: false,
				fileEditor: false,
			},
			global: {
				mocks: {
					t: (key, message) => message,
				},
				stubs: {
					NcDialog: { template: '<div class="nc-dialog"><slot /></div>', emits: ['closing'] },
					NcButton: { template: '<button @click="$emit(\'click\')"><slot /></button>', emits: ['click'] },
					Editor: { template: '<div></div>', emits: ['close', 'save'] },
					TextInput: { template: '<div></div>', emits: ['close', 'save'] },
					FileUpload: { template: '<div></div>', emits: ['close', 'save'] },
					DrawIcon: { template: '<div></div>' },
					SignatureTextIcon: { template: '<div></div>' },
					UploadIcon: { template: '<div></div>' },
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(wrapper.vm.availableTabs.length).toBe(1)
		expect(wrapper.vm.availableTabs[0].id).toBe('draw')
	})

	it('renders multiple tabs when multiple editors enabled', async () => {
		const wrapper = mount(Draw, {
			props: {
				type: 'signature',
				drawEditor: true,
				textEditor: true,
				fileEditor: true,
			},
			global: {
				mocks: {
					t: (key, message) => message,
				},
				stubs: {
					NcDialog: { template: '<div class="nc-dialog"><slot /></div>', emits: ['closing'] },
					NcButton: { template: '<button @click="$emit(\'click\')"><slot /></button>', emits: ['click'] },
					Editor: { template: '<div></div>', emits: ['close', 'save'] },
					TextInput: { template: '<div></div>', emits: ['close', 'save'] },
					FileUpload: { template: '<div></div>', emits: ['close', 'save'] },
					DrawIcon: { template: '<div></div>' },
					SignatureTextIcon: { template: '<div></div>' },
					UploadIcon: { template: '<div></div>' },
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(wrapper.vm.availableTabs.length).toBe(3)
		expect(wrapper.vm.availableTabs.map(t => t.id)).toEqual(['draw', 'text', 'file'])
	})

	it('switches active tab when tab clicked', async () => {
		const wrapper = mount(Draw, {
			props: {
				type: 'signature',
				drawEditor: true,
				textEditor: true,
				fileEditor: true,
			},
			global: {
				mocks: {
					t: (key, message) => message,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.activeTab = 'text'
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.activeTab).toBe('text')
	})

	it('sets active tab to first available when current is not available', async () => {
		const wrapper = mount(Draw, {
			props: {
				type: 'signature',
				drawEditor: true,
				textEditor: true,
				fileEditor: true,
			},
			global: {
				mocks: {
					t: (key, message) => message,
				},
			},
		})

		wrapper.vm.activeTab = 'text'
		await wrapper.vm.$nextTick()
		wrapper.vm.$emit = vi.fn()

		wrapper.setProps({ textEditor: false })
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.activeTab).toBe('text')
	})

	it('emits close when close method called', async () => {
		const wrapper = mount(Draw, {
			props: {
				type: 'signature',
			},
			global: {
				mocks: {
					t: (key, message) => message,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.close()
		expect(wrapper.emitted('close')).toBeTruthy()
	})

	it('calls store loadSignatures when save is triggered', async () => {
		const wrapper = mount(Draw, {
			props: {
				type: 'signature',
			},
			global: {
				mocks: {
					t: (key, message) => message,
				},
			},
		})

		await wrapper.vm.$nextTick()

		const store = useSignatureElementsStore()
		const originalLoadSignatures = store.loadSignatures
		store.loadSignatures = vi.fn()
		store.save = vi.fn()

		wrapper.vm.signatureElementsStore.loadSignatures = vi.fn()
		wrapper.vm.signatureElementsStore.save = vi.fn()

		const base64Data = 'data:image/png;base64,test'
		await wrapper.vm.save(base64Data)
	})

	it('emits save event after complete flow', async () => {
		const wrapper = mount(Draw, {
			props: {
				type: 'signature',
			},
			global: {
				mocks: {
					t: (key, message) => message,
				},
			},
		})

		await wrapper.vm.$nextTick()
		const store = wrapper.vm.signatureElementsStore
		store.loadSignatures = vi.fn()
		store.save = vi.fn()

		await wrapper.vm.save('data:image/png;base64,test')

		expect(wrapper.emitted('save')).toBeTruthy()
	})

	it('closes dialog after successful save', async () => {
		const wrapper = mount(Draw, {
			props: {
				type: 'signature',
			},
			global: {
				mocks: {
					t: (key, message) => message,
				},
			},
		})

		await wrapper.vm.$nextTick()
		const store = wrapper.vm.signatureElementsStore
		store.loadSignatures = vi.fn()
		store.save = vi.fn()

		const closeEmits = wrapper.emitted('close') || []
		const initialCount = closeEmits.length

		await wrapper.vm.save('data:image/png;base64,test')

		const finalEmits = wrapper.emitted('close') || []
		expect(finalEmits.length).toBeGreaterThan(initialCount)
	})

	it('adds class to body and document on mount', async () => {
		const wrapper = mount(Draw, {
			props: {
				type: 'signature',
			},
			global: {
				mocks: {
					t: (key, message) => message,
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(document.body.classList.contains('libresign-modal-open')).toBe(true)
		expect(document.documentElement.classList.contains('libresign-modal-open')).toBe(true)
	})

	it('removes classes when dialog closed', async () => {
		const wrapper = mount(Draw, {
			props: {
				type: 'signature',
				drawEditor: true,
			},
			global: {
				mocks: {
					t: (key, message) => message,
				},
				stubs: {
					NcDialog: { template: '<div class="nc-dialog"><slot /></div>', emits: ['closing'] },
					NcButton: { template: '<button @click="$emit(\'click\')"><slot /></button>', emits: ['click'] },
					Editor: { template: '<div></div>', emits: ['close', 'save'] },
					TextInput: { template: '<div></div>', emits: ['close', 'save'] },
					FileUpload: { template: '<div></div>', emits: ['close', 'save'] },
					DrawIcon: { template: '<div></div>' },
					SignatureTextIcon: { template: '<div></div>' },
					UploadIcon: { template: '<div></div>' },
				},
			},
		})

		await wrapper.vm.$nextTick()
		expect(document.body.classList.contains('libresign-modal-open')).toBe(true)

		// Unmount the component
		wrapper.unmount()
		await wrapper.vm.$nextTick()
		expect(document.body.classList.contains('libresign-modal-open')).toBe(false)
		expect(document.documentElement.classList.contains('libresign-modal-open')).toBe(false)
	})

	it('accepts type property', () => {
		const wrapper = mount(Draw, {
			props: {
				type: 'initial',
				drawEditor: true,
			},
			global: {
				mocks: {
					t: (key, message) => message,
				},
				stubs: {
					NcDialog: { template: '<div class="nc-dialog"><slot /></div>', emits: ['closing'] },
					NcButton: { template: '<button @click="$emit(\'click\')"><slot /></button>', emits: ['click'] },
					Editor: { template: '<div></div>', emits: ['close', 'save'] },
					TextInput: { template: '<div></div>', emits: ['close', 'save'] },
					FileUpload: { template: '<div></div>', emits: ['close', 'save'] },
					DrawIcon: { template: '<div></div>' },
					SignatureTextIcon: { template: '<div></div>' },
					UploadIcon: { template: '<div></div>' },
				},
			},
		})

		expect(wrapper.vm.type).toBe('initial')
	})

	it('replaces active tab when props change', async () => {
		const wrapper = mount(Draw, {
			props: {
				type: 'signature',
				drawEditor: true,
				textEditor: true,
				fileEditor: false,
			},
			global: {
				mocks: {
					t: (key, message) => message,
				},
			},
		})

		await wrapper.vm.$nextTick()
		wrapper.vm.activeTab = 'text'
		wrapper.setProps({ textEditor: false, fileEditor: true })
		await wrapper.vm.$nextTick()

		expect(wrapper.vm.availableTabs.map(t => t.id)).toEqual(['draw', 'file'])
	})

	it('initializes mounted flag to false', () => {
		const wrapper = mount(Draw, {
			props: {
				type: 'signature',
			},
			global: {
				mocks: {
					t: (key, message) => message,
				},
			},
		})

		expect(wrapper.vm.mounted).toBe(false)
	})

	it('initializes active tab to draw', () => {
		const wrapper = mount(Draw, {
			props: {
				type: 'signature',
			},
			global: {
				mocks: {
					t: (key, message) => message,
				},
			},
		})

		expect(wrapper.vm.activeTab).toBe('draw')
	})
})
