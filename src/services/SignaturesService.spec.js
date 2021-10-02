import { generateUrl } from '@nextcloud/router'
import mockAxios from '../__test__/__mocks__/axios'
import { deleteElement, getElement, loadSignatures, newSignature, updateElement } from './SignaturesService'

describe('SignaturesService', () => {
	afterEach(() => {
		mockAxios.reset()
	})

	test('loadSignatures, call to API for the fetch all signatures', () => {
		const response = [
			{
				type: 'signature',
				file: {
					url: 'http://cloud.test.coop/s/ry384r6t384/download/signature.png',
				},
				id: 5,
			}, {
				type: 'initials',
				file: {
					url: 'http://cloud.test.coop/s/ry384r6t384/download/signature.png',
				},
				id: 5,
			},
		]

		loadSignatures()

		expect(mockAxios.get).toHaveBeenCalled()
		expect(mockAxios.get).toHaveBeenCalledWith(
			generateUrl('/apps/libresign/api/0.1/account/signatures/elements')
		)

		mockAxios.mockResponse(response)

		expect(mockAxios.get).toHaveReturnedTimes(1)

	})

	test('newSignature, call to API to create a new Signature', () => {
		newSignature('signature', 'base64')

		expect(mockAxios.post).toHaveBeenCalledTimes(1)
		expect(mockAxios.post).toHaveBeenCalledWith(
			generateUrl('/apps/libresign/api/0.1/account/signatures/elements', {
				type: 'signature',
				file: {
					base64: 'base64',
				},
			}))

		mockAxios.mockResponse({
			type: 'signature',
			file: {
				base64: 'base64',
			},
		})
		expect(mockAxios.post).toHaveReturnedTimes(1)
		expect(mockAxios.post).toHaveReturnedWith(
			expect.objectContaining({
				_data: expect.objectContaining({
					status: 200,
				}),
			})
		)
	})

	test('getElement, call to API to get a single Signature', () => {
		const response = {
			type: 'signature',
			file: {
				url: 'http://cloud.test.coop/s/ry384r6t384/download/signature.png',
			},
			id: 5,
		}
		getElement(5)
		expect(mockAxios.get).toHaveBeenCalled()
		expect(mockAxios.get).toHaveBeenCalledTimes(1)
		expect(mockAxios.get).toHaveBeenCalledWith(
			generateUrl('/apps/libresign/api/0.1/account/signatures/elements/5')
		)
		mockAxios.mockResponse(response)
		expect(mockAxios.get).toHaveReturned()
		expect(mockAxios.get).toHaveReturnedTimes(1)
	})

	test('updateElement', () => {
		const response = {
			type: 'signature',
			file: {
				url: 'http://cloud.test.coop/s/ry384r6t384/download/signature.png',
			},
			id: 5,
		}

		updateElement(5)

		expect(mockAxios.patch).toHaveBeenCalled()
		expect(mockAxios.patch).toHaveBeenCalledTimes(1)
		expect(mockAxios.patch).toHaveBeenCalledWith(
			generateUrl('/apps/libresign/api/0.1/account/signatures/elements/5', {
				type: 'signature',
				file: {
					base64: 'base64',
				},
			})
		)
		mockAxios.mockResponse(response)
		expect(mockAxios.patch).toHaveReturned()
		expect(mockAxios.patch).toHaveReturnedTimes(1)
		expect(mockAxios.patch).toHaveReturnedWith(
			expect.objectContaining({
				_data: expect.objectContaining({
					status: 200,
				}),
			})
		)
	})

	test('deleteElement', () => {
		deleteElement(5)
		expect(mockAxios.delete).toHaveBeenCalled()
		expect(mockAxios.delete).toHaveBeenCalledTimes(1)
		expect(mockAxios.delete).toHaveBeenCalledWith(
			generateUrl('/apps/libresign/api/0.1/account/signatures/elements/5')
		)
		expect(mockAxios.delete).toHaveReturned()
		expect(mockAxios.delete).toHaveReturnedTimes(1)
	})
})
