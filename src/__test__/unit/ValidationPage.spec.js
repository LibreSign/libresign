import { shallowMount, createLocalVue } from '@vue/test-utils'
import VueRouter from 'vue-router'
import { generateOcsUrl } from '@nextcloud/router'
import ValidationPage from '../../views/Validation.vue'
import mockAxios from '../__mocks__/axios'

let wrapper
const localVue = createLocalVue()
localVue.use(VueRouter)

const router = new VueRouter()
const OC = () => {
	return window.OC
}

beforeEach(() => {
	wrapper = shallowMount(ValidationPage, {
		localVue,
		stubs: ['router-view', 'initial'],
		router,
		mocks: {
			OC,
		},
	})
})

afterEach(() => {
	wrapper.destroy()
	mockAxios.reset()
})

describe('Validation Page without API data ', () => {
	it('Is a Vue Instance', () => {
		expect(wrapper.vm).toBeTruthy()
	})

	it('UUID initialized empty', async() => {
		expect(wrapper.props().uuid).toBe('')

		const input = wrapper.find('input')
		expect(input.exists()).toBe(true)
		await input.setValue('')
		expect(input.element.value).toBe('')
		expect(wrapper.vm.hasInfo).toBe(false)
	})

})

describe('Validation Page with API data', () => {
	const mockDocument = {
		success: true,
		name: 'ssd',
		createdAt: '1628009687',
		file: '/index.php/apps/libresign/pdf/c8afa0a9-7e45-40a5-905e-defb3e3fc2be',
		signers: [{
			signed: '1628014335',
			displayName: '',
			fullName: null,
			me: false,
			signatureId: 146,
			email: 'ad3@sd.c',
		}, {
			signed: null,
			displayName: '',
			fullName: null,
			me: false,
			signatureId: 165,
			email: '4@sd.com',
		}],
		settings: {
			canSign: true,
			canRequestSign: true,
			hasSignatureFile: true,
		},
	}

	beforeEach(async() => {
		await wrapper.setData({ document: mockDocument })
		await wrapper.setData({ hasInfo: true })
	})

	it('Get Legal information', async() => {
		expect(wrapper.vm.hasInfo).toBe(true)

		expect(mockAxios.get).toHaveBeenCalledWith(
			generateOcsUrl('/apps/provisioning_api/api/v1', 2) + 'config/apps/libresign/legal_information', {})
		mockAxios.mockResponse({
			data: {
				ocs: {
					data: {
						data: 'This is a message',
					},
				},
			},
		})

		// Need to wait for re-render, otherwise the list is not rendered yet
		await wrapper.vm.$nextTick()
		const legalInformation = wrapper.findAll('span.legal-information')
		expect(legalInformation.at(0).text()).toBe('This is a message')
	})

	it('Rendering signers list', () => {
		expect(wrapper.vm.hasInfo).toBe(true)

		const listGroups = wrapper.findAll('div.scroll')
		expect(listGroups.exists()).toBe(true)
	})

	it('Button to view Document', () => {
		const button = wrapper.find('a.button')
		expect(button.exists()).toBe(true)
		expect(button.attributes('href')).toBe(mockDocument.file)
	})

})
