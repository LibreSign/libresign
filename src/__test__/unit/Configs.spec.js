import { shallowMount, createLocalVue } from '@vue/test-utils'
import VueRouter from 'vue-router'
import Settings from '../../views/Settings.vue'

let wrapper
const localVue = createLocalVue()
localVue.use(VueRouter)
const router = new VueRouter()

const OC = () => {
	return window.OC
}

beforeEach(() => {
	wrapper = shallowMount(Settings, {
		localVue,
		stubs: ['router-view'],
		router,
		mocks: {
			OC,
		},
	})
})

afterEach(() => {
	wrapper.destroy()
})

describe('Legal Information', () => {
	it('Is a Vue Instance', () => {
		expect(wrapper.vm).toBeTruthy()
	})

	it('Legal information initialized empty', () => {
		expect(wrapper.vm.legalInformation).toBe('')
	})
})
