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

describe('Validation Page', () => {
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
		}, {
			signed: '1628014488',
			displayName: 'asd@asd.coop',
			fullName: null,
			me: false,
			signatureId: 147,
			email: 'asd@asd.coop',
		}, {
			signed: '1628019897',
			displayName: 'algume-amidslacomqweleasdja2teste.coop@teste.coop',
			fullName: null,
			me: false,
			signatureId: 161,
			email: 'algume-amidslacomqweleasdja2teste.coop@teste.coop',
		}, {
			signed: '1628014987',
		 displayName: '',
			fullName: null,
			me: false,
			signatureId: 148,
			email: '1@b.c',
		}, {
			signed: '1628015162',
			displayName: '',
			fullName: null,
			me: false,
			signatureId: 149,
			email: '2@b.com',
		}, {
			signed: '1628016442',
			displayName: 'd@d.coop',
			fullName: null,
			me: false,
			signatureId: 150,
			email: 'd@d.coop',
		}, {
			signed: '1628022374',
			displayName: '',
			fullName: null,
			me: false,
			signatureId: 157,
			email: 'asd@sdsd.c',
		}, {
			signed: '1628017237',
			displayName: '',
			fullName: null,
			me: false,
			signatureId: 151,
			email: 'dd@d.coop',
		}, {
			signed: '1628017367',
			displayName: '',
			fullName: null,
			me: false,
			signatureId: 145,
			email: 'adm@adm.coops',
		}, {
			signed: null,
			displayName: '',
			fullName: null,
			me: false,
			signatureId: 166,
			email: 'l@l.c',
		}, {
			signed: null,
			displayName: '',
			fullName: null,
			me: false,
			signatureId: 155,
			email: 'd@sdsd.com',
		}, {
			signed: null,
			displayName: '',
			fullName: null,
			me: false,
			signatureId: 156,
			email: 'sdalksd@sda.c',
		}, {
			signed: null,
			displayName: '',
			fullName: null,
			me: false,
			signatureId: 158,
			email: 'dwww@ww.c',
		}, {
			signed: null,
			displayName: '',
			fullName: null,
			me: false,
			signatureId: 160,
			email: 'd2@d2.d',
		}, {
			signed: '1628023125',
			displayName: '',
			fullName: null,
			me: false,
			signatureId: 159,
			email: 'ealkwee@ds.w',
		}, {
			signed: null,
			displayName: 'nextcloud',
			fullName: null,
			me: true,
			signatureId: 162,
			email: 'admin@admin.com',
		}, {
			signed: null,
			displayName: '',
			fullName: null,
			me: false,
			signatureId: 163,
			email: 'asdf@d.coop',
		}, {
			signed: null,
			displayName: '',
			fullName: null,
			me: false,
			signatureId: 164,
			email: '3@ds.com',
		}, {
			signed: '1628102190',
			displayName: '',
			fullName: null,
			me: false,
			signatureId: 167,
			email: 's@s.c',
		}],
		settings: {
			canSign: true,
			canRequestSign: true,
			hasSignatureFile: true,
		},
	}

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

	it('Have data', async() => {
		await wrapper.setData({ document: mockDocument })
		await wrapper.setData({ hasInfo: true })

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
})
