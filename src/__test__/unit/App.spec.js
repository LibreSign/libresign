import { shallowMount, createLocalVue } from '@vue/test-utils'
import VueRouter from 'vue-router'
import App from '../../App.vue'

let wrapper
const localVue = createLocalVue()
localVue.use(VueRouter)
const router = new VueRouter()

const OC = () => {
	return window.OC
}

beforeEach(() => {
	wrapper = shallowMount(App, {
		localVue,
		stubs: ['router-linkn', 'router-view'],
		router,
		mocks: {
			OC,
		},
	})
})

afterEach(() => {
	wrapper.destroy()
})

describe('App', () => {
	test('Is a Vue Instance', () => {
		expect(wrapper.vm).toBeTruthy()
	})
})
