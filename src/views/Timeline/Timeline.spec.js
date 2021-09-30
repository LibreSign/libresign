import { shallowMount, createLocalVue } from '@vue/test-utils'
import VueRouter from 'vue-router'
import Timeline from './Timeline.vue'
import store from '../../store'
import Vuex from 'vuex'

let wrapper
const localVue = createLocalVue()
localVue.use(VueRouter)
localVue.use(Vuex)
const router = new VueRouter()

jest.mock('@nextcloud/initial-state', () => ({
	loadState: jest.fn().mockReturnValue('{"settings":{"hasSignatureFile":true}}'),
}))

const OC = () => {
	return window.OC
}

beforeEach(() => {
	wrapper = shallowMount(Timeline, {
		localVue,
		stubs: ['router-view'],
		router,
		store,
		mocks: {
			OC,
		},
	})
})

afterEach(() => {
	wrapper.destroy()
})

describe('Timeline', () => {
	it('Is a Vue Instance', () => {
		expect(wrapper.vm).toBeTruthy()
	})

	it('Files start empty', () => {
		expect(store.state.files.files).toEqual([])
		expect(wrapper.vm.emptyContentFile).toBe(true)
		expect(wrapper.vm.sidebar).toBe(false)
	})

	it('If have Files', () => {
		store.state.files.files = [
			{
				uuid: '83adaa74-d110-4503-a067-dc7481f062d1',
				name: 'sample',
				callback: null,
				request_date: '2021-08-26 16:04:04',
				status_date: '2021-08-26 16:15:20',
				requested_by: {
					uid: 'admin',
					displayName: null,
				},
				file: {
					type: 'pdf',
					url: '/apps/libresign/pdf/user/83adaa74-d110-4503-a067-dc7481f062d1',
					nodeId: 88,
				},
				signers: [
					{
						email: 'admin@admin.coop',
						description: 't',
						displayName: 'admin',
						request_sign_date: '2021-08-26 16:04:04',
						sign_date: '2021-08-26 16:15:20',
						uid: 'admin',
						signatureId: 1,
						me: false,
					}, {
						email: 't@t.coop',
						description: null,
						displayName: '',
						request_sign_date: '2021-08-26 19:41:47',
						sign_date: null,
						uid: null,
						signatureId: 7,
						me: false,
					},
				],
				status: 'pending',
			},
		]

		expect(wrapper.vm.emptyContentFile).toBe(false)
		expect(wrapper.vm.filterFile.length > 0).toBe(true)
		expect(wrapper.vm.sidebar).toBe(false)
	})

	it('Click to see File info', async() => {
		const file = {
			uuid: '83adaa74-d110-4503-a067-dc7481f062d1',
			name: 'sample',
			callback: null,
			request_date: '2021-08-26 16:04:04',
			status_date: '2021-08-26 16:15:20',
			requested_by: {
				uid: 'admin',
				displayName: null,
			},
			file: {
				type: 'pdf',
				url: '/apps/libresign/pdf/user/83adaa74-d110-4503-a067-dc7481f062d1',
				nodeId: 88,
			},
			signers: [
				{
					email: 'admin@admin.coop',
					description: 't',
					displayName: 'admin',
					request_sign_date: '2021-08-26 16:04:04',
					sign_date: '2021-08-26 16:15:20',
					uid: 'admin',
					signatureId: 1,
					me: false,
				}, {
					email: 't@t.coop',
					description: null,
					displayName: '',
					request_sign_date: '2021-08-26 19:41:47',
					sign_date: null,
					uid: null,
					signatureId: 7,
					me: false,
				},
			],
			status: 'pending',
		}

		wrapper.vm.$emit('sidebar', file)

		expect(wrapper.emitted().sidebar).toBeTruthy()
		await wrapper.vm.$nextTick()
		wrapper.vm.setSidebar()
		expect(wrapper.vm.statusSidebar).toBe(true)
	})
})
