import { generateUrl } from '@nextcloud/router'
import store from '../store'

export class SignaturesService {

	url(url) {
		url = `/apps/libresign/api/0.1${url}`
		return generateUrl(url)
	}

	async loadSignatures() {
		return [
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
	}

	async newSignature() {}

	async getElement(elementId) {
		return {
			type: 'signature',
			file: {
				url: 'http://cloud.test.coop/s/ry384r6t384/download/signature.png',
			},
			id: 5,
			metadata: {
				text: 'string',
				dateFormat: 'string',
			},
		}
	}

	async updateElement(element) {
		const oldElement = this.getElement(element.id)

		if (oldElement.id === element.id) {
			if (element.type === 'signature') {
				store.commit('signatures/setSignature', element)
			} else {
				store.commit('signatures/setInitials', element)
			}
		}
	}

	async newElement(element) {
		if (element.type === 'signature') {
			store.commit('signatures/setSignature', element)
		} else {
			store.commit('signatures/setInitials', element)
		}
		return { message: 'Success' }
	}

	async deleteElement(elementId) {

	}

}
