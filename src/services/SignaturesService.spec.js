import { generateUrl } from '@nextcloud/router'
import mockAxios from '../__test__/__mocks__/axios'
import { getElement, loadSignatures, newSignature } from './SignaturesService'

describe('SignaturesService', () => {
	afterEach(() => {
		mockAxios.reset()
	})

	test('loadSignatures, call to API for the fetch all signatures', () => {
		mockAxios.get.mockImplementationOnce(() =>
			Promise.resolve({
				data: {
					signatures: [
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
					],
				},
			}))
		const signatures = loadSignatures([
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
		])

		expect(signatures).toEqual([])
		expect(mockAxios.get).toHaveBeenCalledTimes(1)
		expect(mockAxios.get).toHaveBeenCalledWith(
			generateUrl('/apps/libresign/api/0.1/account/signatures/elements')
		)
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
	})

	test('getElement, call to API to get a Signature element', () => {
		mockAxios.get.mockImplementationOnce(() =>
			Promise.resolve({
				data: {
					type: 'signature',
					file: {
						url: 'http://cloud.test.coop/s/ry384r6t384/download/signature.png',
					},
					id: 5,
					metadata: {
						text: 'string',
						dateFormat: 'string',
					},
				},
			}))
	})

	const signature = getElement(132)

	expect(signature).toEqual({
		type: 'signature',
		file: {
			url: 'http://cloud.test.coop/s/ry384r6t384/download/signature.png',
		},
		id: 5,
		metadata: {
			text: 'string',
			dateFormat: 'string',
		},
	})

	expect(mockAxios.get).toHaveBeenCalledTimes(1)
	expect(mockAxios.get).toHaveBeenCalledWith(
		generateUrl('/apps/libresign/api/0.1/account/signatures/elements/132')
	)

})
