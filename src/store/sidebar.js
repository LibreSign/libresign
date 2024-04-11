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
import { set } from 'vue'

export const useSidebarStore = defineStore('sidebar', {
	state: () => ({
		show: false,
		activeTab: '',
	}),

	actions: {
		canShow() {
			return this.show === false && this.activeTab.length > 0
		},
		isVisible() {
			return this.show === true && this.activeTab.length > 0
		},
		showSidebar() {
			set(this, 'show', true)
		},
		activeSignTab() {
			set(this, 'activeTab', 'sign-tab')
			this.showSidebar()
		},
		activeRequestSignatureTab() {
			set(this, 'activeTab', 'request-signature-tab')
			this.showSidebar()
		},
		setActiveTab(id) {
			set(this, 'activeTab', id ?? '')
			if (id) {
				this.hideSidebar()
			} else {
				this.showSidebar()
			}
		},
		hideSidebar() {
			set(this, 'show', false)
		},
	},
})
