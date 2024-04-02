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
import { loadState } from '@nextcloud/initial-state'

const defaultState = {
	errors: [],
	document: {
		name: '',
		description: '',
		status: '',
		statusText: '',
		url: '',
		uuid: '',
		signers: [],
		visibleElements: [],
	},
}

export const useSignStore = defineStore('sign', {
	state: () => ({ ...defaultState }),

	actions: {
		initFromState() {
			this.errors = loadState('libresign', 'errors', [])
			const pdf = loadState('libresign', 'pdf', [])
			this.document = {
				name: loadState('libresign', 'filename'),
				description: loadState('libresign', 'description', ''),
				status: loadState('libresign', 'status'),
				statusText: loadState('libresign', 'statusText'),
				url: pdf.url,
				uuid: loadState('libresign', 'uuid', null),
				signers: loadState('libresign', 'signers', []),
				visibleElements: loadState('libresign', 'visibleElements', []),
			}
		},
		reset() {
			Object.assign(this, defaultState);
		}
	},
})
