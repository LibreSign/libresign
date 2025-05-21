/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { set } from 'vue'

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
		settings: [],
	}),
	actions: {
		closeModal(modalCode) {
			set(this.modal, modalCode, false)
		},
		showModal(modalCode) {
			set(this.modal, modalCode, true)
		},
		blurredEmail() {
			return this.settings?.emailToken?.blurredEmail ?? ''
		},
		setHasEmailConfirmCode(hasConfirmCode) {
			if (!Object.hasOwn(this.settings, 'emailToken')) {
				set(this.settings, 'emailToken', {})
			}
			set(this.settings.emailToken, 'hasConfirmCode', hasConfirmCode)
		},
		setEmailToken(token) {
			if (!Object.hasOwn(this.settings, 'emailToken')) {
				set(this.settings, 'emailToken', {})
			}
			set(this.settings.emailToken, 'token', token)
		},
		hasSignatureFile() {
			return Object.hasOwn(this.settings, 'password')
				&& Object.hasOwn(this.settings.password, 'hasSignatureFile')
				&& this.settings.password.hasSignatureFile
		},
		setHasSignatureFile(hasSignatureFile) {
			if (!Object.hasOwn(this.settings, 'password')) {
				set(this.settings, 'password', {})
			}
			set(this.settings.password, 'hasSignatureFile', hasSignatureFile)
		},
		needCreatePassword() {
			return this.needSignWithPassword()
				&& (
					!Object.hasOwn(this.settings, 'password')
					|| !Object.hasOwn(this.settings.password, 'hasSignatureFile')
					|| !this.settings.password.hasSignatureFile
				)
		},
		needSignWithPassword() {
			return Object.hasOwn(this.settings, 'password')
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
