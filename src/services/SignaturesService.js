import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import store from '../store/index.js'

export const loadSignatures = async () => {
	const response = await axios.get(
		generateUrl('/apps/libresign/api/0.1/account/signatures/elements')
	)
	return response
}

export const newSignature = async (type, base64) => {
	const response = await axios.post(
		generateUrl('/apps/libresign/api/0.1/account/signatures/elements', {
			type,
			file: {
				base64,
			},
		})
	)
	return response
}

export const getElement = async (elementId) => {
	const response = await axios.get(
		generateUrl(`/apps/libresign/api/0.1/account/signatures/elements/${elementId}`)
	)
	store.commit('signatures/setSignature', response.data)
	return response
}

export const updateElement = async (element) => {
	const response = await axios.patch(
		generateUrl(`/apps/libresign/api/0.1/account/signatures/elements/${element.id}`, {
			element,
		})
	)
	store.commit('signatures/setSignature', element)
	return response
}

export const newElement = async (element) => {
	if (element.type === 'signature') {
		store.commit('signatures/setSignature', element)
	} else {
		store.commit('signatures/setInitials', element)
	}
	return { message: 'Success' }
}

export const deleteElement = async (elementID) => {
	const response = await axios.delete(
		generateUrl(`/apps/libresign/api/0.1/account/signatures/elements/${elementID}`)
	)
	return response
}
