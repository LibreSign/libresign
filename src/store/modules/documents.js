import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

const state = {
	documents: [],
}

const getters = {
	documents: () => {
		return [
			{ name: 'Passaporte' },
			{ name: 'RG', status: 'approval' },
			{ name: 'CPF', status: 'approved' },
			{ name: 'CNH', status: 'reproved' },
		]
	},
	fileTypes: () => [
		{
			name: 'JPEG', type: 'image/jpeg, image/jpg',
		},
		{
			name: 'PNG', type: 'png',
		},
		{
			name: 'PDF', type: 'pdf',
		},
	],
}

const mutations = {}

const actions = {
	async list(context, payload) {
		let result
		try {
			result = await axios.get(generateUrl('/apps/libresign/api/0.1/account/files/list'))
		} catch (err) {
			showError('Error while trying to list')
			return { success: false }
		}

		return { success: true, data: result }
	},
	async remove(context, payload) {
		let result
		try {
			result = await axios.delete(generateUrl(`/apps/libresign/api/0.1/account/files/${payload.id}`))
		} catch (err) {
			showError('Error while trying to delete')
			context.commit('setError', err.response.data.message)
			return { success: false }
		}

		return { success: true, data: result }
	},
	async save(context, payload) {
		let result
		try {
			result = await axios.post(generateUrl('/apps/libresign/api/0.1/account​/files'), payload.form)
		} catch (err) {
			showError('Error while trying to save')
			context.commit('setError', err.response.data.message)
			return { success: false }
		}

		return { success: true, data: result }
	},
}

export default {
	namespaced: true,
	state,
	getters,
	mutations,
	actions,
}
