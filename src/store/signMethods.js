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
import { loadState } from '@nextcloud/initial-state'

export const useSignMethodsStore = defineStore('signMethods', {
	state: () => ({
		modal: {
			emailToken: false,
			clickToSign: false,
			createPassword: false,
			signPassword: false,
			createSignature: false,
			sms: false,
		},
		settings: loadState('libresign', 'signature_methods', []),
	}),
	actions: {
		closeModal(modalCode) {
			set(this.modal, modalCode, false)
		},
		showModal(modalCode) {
			set(this.modal, modalCode, true)
		},
		blurredEmail() {
			return this.settings.emailToken.blurredEmail
		},
		hasEmailConfirmCode(hasConfirmCode) {
			set(this.settings.emailToken, 'hasConfirmCode', hasConfirmCode)
		},
		hasSignatureFile(hasSignatureFile) {
			set(this.signMethodsStore.settings.password, 'hasSignatureFile', hasSignatureFile)
		},
		needCreatePassword() {
			return Object.hasOwn(this.settings, 'password')
				&& !this.settings.password.hasSignatureFile
		},
		needEmailCode() {
			return Object.hasOwn(this.settings, 'emailToken')
				&& this.settings.emailToken.needCode
		},
		needClickToSign() {
			return Object.hasOwn(this.settings, 'clickToSign')
		},
		needSmsCode() {
			return Object.hasOwn(this.settings, 'sms')
				&& this.settings.sms.needCode
		},
	},
})
