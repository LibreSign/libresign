/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useSignMethodsStore = defineStore('signMethods', () => {
	const modal = ref({
		emailToken: false,
		clickToSign: false,
		createPassword: false,
		signPassword: false,
		createSignature: false,
		password: false,
		token: false, // Generic token modal for all token-based methods
		uploadCertificate: false,
		readCertificate: false,
		resetPassword: false,
	})
	const settings = ref({})
	const certificateEngine = ref(loadState('libresign', 'certificate_engine', ''))

	const closeModal = (modalCode) => {
		modal.value[modalCode] = false
	}

	const showModal = (modalCode) => {
		modal.value[modalCode] = true
	}

	const blurredEmail = () => settings.value?.emailToken?.blurredEmail ?? ''

	const setHasEmailConfirmCode = (hasConfirmCode) => {
		if (!Object.hasOwn(settings.value, 'emailToken')) {
			settings.value.emailToken = {}
		}
		settings.value.emailToken.hasConfirmCode = hasConfirmCode
	}

	const setEmailToken = (token) => {
		if (!Object.hasOwn(settings.value, 'emailToken')) {
			settings.value.emailToken = {}
		}
		settings.value.emailToken.token = token
	}

	const hasSignatureFile = () => {
		return Object.hasOwn(settings.value, 'password')
			&& Object.hasOwn(settings.value.password, 'hasSignatureFile')
			&& settings.value.password.hasSignatureFile
	}

	const setHasSignatureFile = (hasSignatureFile) => {
		if (!Object.hasOwn(settings.value, 'password')) {
			settings.value.password = {}
		}
		settings.value.password.hasSignatureFile = hasSignatureFile
	}

	const needSignWithPassword = () => Object.hasOwn(settings.value, 'password')

	const needCreatePassword = () => needSignWithPassword() && !hasSignatureFile()

	const needEmailCode = () => {
		return Object.hasOwn(settings.value, 'emailToken')
			&& settings.value.emailToken.needCode
	}

	const needClickToSign = () => Object.hasOwn(settings.value, 'clickToSign')

	const needSmsCode = () => {
		return Object.hasOwn(settings.value, 'smsToken')
			&& settings.value.smsToken.needCode
	}

	const needTokenCode = () => {
		const tokenMethods = ['smsToken', 'whatsappToken', 'signalToken', 'telegramToken', 'xmppToken']
		return tokenMethods.some(method =>
			Object.hasOwn(settings.value, method) && settings.value[method].needCode
		)
	}

	const needCertificate = () => certificateEngine.value === 'none' && !hasSignatureFile()

	return {
		modal,
		settings,
		certificateEngine,
		closeModal,
		showModal,
		blurredEmail,
		setHasEmailConfirmCode,
		setEmailToken,
		hasSignatureFile,
		setHasSignatureFile,
		needCreatePassword,
		needSignWithPassword,
		needEmailCode,
		needClickToSign,
		needSmsCode,
		needTokenCode,
		needCertificate,
	}
})
