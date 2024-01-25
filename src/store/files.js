/*
 * @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

import { defineStore } from 'pinia'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import Vue from 'vue'
import { showError } from '@nextcloud/dialogs'

export const useFilesStore = defineStore('files', {
	state: () => ({
		files: {},
		file: {},
	}),

	actions: {
		addFile(file) {
			Vue.set(this.files, file.nodeId, file)
		},
		selectFile(uuid) {
			this.file = this.files[uuid]
		},
		async getAllFiles() {
			try {
				const response = await axios.get(generateOcsUrl('/apps/libresign/api/v1/file/list'))
				response.data.data.forEach(file => {
					this.addFile(file)
				})
			} catch (err) {
				showError('An error occurred while fetching the files')
			}
		},
		pendingFilter() {
			return Object.values(this.files).filter(
				(a) => (a.status === 2)).sort(
				(a, b) => (a.request_date < b.request_date) ? 1 : -1)
		},
		signedFilter() {
			return Object.values(this.files).filter(
				(a) => (a.status === 1)).sort(
				(a, b) => (a.request_date < b.request_date) ? 1 : -1)
		},
		orderFiles() {
			return Object.values(this.files).sort((a, b) => (a.request_date < b.request_date) ? 1 : -1)
		},
	}
})
