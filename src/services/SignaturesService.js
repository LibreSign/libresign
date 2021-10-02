import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import store from '../store'

export const loadSignatures = async() => {
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

export const newSignature = async(type, base64) => {
	const response = await axios.post(
		generateUrl('/apps/libresign/api/0.1/account/signatures/elements', {
			type,
			file: {
				base64,
			},
		})
	)

	console.info(response)
}

export const getElement = async(elementId) => {
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

export const updateElement = async(element) => {
	const oldElement = this.getElement(element.id)

	if (oldElement.id === element.id) {
		if (element.type === 'signature') {
			store.commit('signatures/setSignature', element)
		} else {
			store.commit('signatures/setInitials', element)
		}
	}
}

export const newElement = async(element) => {
	if (element.type === 'signature') {
		store.commit('signatures/setSignature', element)
	} else {
		store.commit('signatures/setInitials', element)
	}
	return { message: 'Success' }
}

export const deleteElement = async(elementId) => {
}
