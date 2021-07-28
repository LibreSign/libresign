import { shallowMount, createLocalVue } from '@vue/test-utils'
import Settings from '../../views/Settings.vue'

let wrapper
const localVue = createLocalVue()

beforeEach(() => {
	wrapper = shallowMount(Settings, {
		localVue,
		stubs: ['router-link', 'router-view'],
	})
})

afterEach(() => {
	wrapper.destroy()
})

describe('Settings', () => {
	test('Is a Vue Instance', () => {
		expect(wrapper.vm).toBeTruthy()
	})
})
