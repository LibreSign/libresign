import { generateUrl } from '@nextcloud/router'
// eslint-disable-next-line node/no-unpublished-import
import { uuid } from 'uuidv4'
import mockAxios from '../__test__/__mocks__/axios.js'
import { fetchDocuments } from './FilesService.js'

describe('FilesService', () => {
	afterEach(() => {
		mockAxios.reset()
	})

	test('FetchDocuments, call to API for the fetch all documents to sign', () => {
		const uid = uuid()
		const response = {
			success: true,
			name: 'filename',
			file: 'http://cloud.test.coop/apps/libresign/pdf/46d30465-ae11-484b-aad5-327249a1e8ef',
			pages: [{
				url: 'http://cloud.test.coop/apps/libresign/img/46d30465-ae11-484b-aad5-327249a1e8ef/page/1',
			}],
			visibleElements: {
				type: 'signature',
				file: {
					url: 'http://cloud.test.coop/s/ry384r6t384/download/signature.png',
				},
			},
			signers: [{
				signed: '2021-12-31 22:45:50',
				displayName: 'John',
				fullName: 'John Doe',
				me: true,
				email: 'user@test.coop',
				fileUserId: 1,
			}],
			settings: {
				canRequestSign: true,
				hasSignatureFile: true,
				canSign: true,
			},
			messages: [{
				type: 'success',
				message: 'string',
			}],
		}

		fetchDocuments(uid)

		expect(mockAxios.get).toHaveBeenCalled()
		expect(mockAxios.get).toHaveBeenCalledTimes(1)
		expect(mockAxios.get).toHaveBeenCalledWith(
			generateUrl(`/apps/libresign/api/0.1/file/validate/uuid/${uid}`)
		)
		mockAxios.mockResponse(response)
		expect(mockAxios.get).toHaveReturnedTimes(1)
	})
})
