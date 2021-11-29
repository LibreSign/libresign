<script>
import { get, isEmpty, pick } from 'lodash-es'
import { showError } from '@nextcloud/dialogs'
import { service as sigantureService } from '../../../domains/signatures'
import { service as signService } from '../../../domains/sign'
import { onError } from '../../../helpers/errors'
import PasswordManager from './ModalPasswordManager.vue'
import SMSManager from './ModalSMSManager.vue'

const SIGN_METHODS = Object.freeze({
	PASSWORD: 'PasswordManager',
	EMAIL: 'EmailManager',
	SMS: 'SMSManager',
})

export default {
	name: 'Sign',
	SIGN_METHODS,
	components: {
		PasswordManager,
		SMSManager,
	},
	props: {
		uuid: {
			type: String,
			required: true,
		},
		document: {
			type: Object,
			required: true,
		},
	},
	data: () => ({
		loading: true,
		modals: {
			password: false,
			email: false,
		},
		user: {
			account: { uid: '', displayName: '' },
			settings: { canRequestSign: false, hasSignatureFile: true },
		},
		userSignatures: [],
	}),
	computed: {
		signer() {
			return this.document?.signers.find(row => row.me) || {}
		},
		visibleElements() {
			const { fileUserId } = this.signer

			if (!fileUserId) {
				return []
			}

			return (this.document?.visibleElements || [])
				.filter(row => row.fileUserId === this.signer.fileUserId)
		},
		signature() {
			return this.userSignatures.find(row => {
				return row.type === 'signature'
			}) ?? {}
		},
		elements() {
			const { signature, visibleElements } = this

			const url = get(signature, ['file', 'url'])
			const id = get(signature, ['id'])

			return visibleElements
				.map(el => ({
					documentElementId: el.elementId,
					profileElementId: id,
					url: `${url}&_t=${Date.now()}`,
				}))
		},
		hasSignatures() {
			return !isEmpty(this.userSignatures)
		},
		needSignature() {
			return !isEmpty(this.document?.visibleElements)
		},
		hasPassword() {
			return !!this.user?.settings?.hasSignatureFile
		},
		singPayload() {
			const elements = this.elements
				.map(row => ({
					documentElementId: row.documentElementId,
					profileElementId: row.profileElementId,
				}))

			const payload = { fileId: this.uuid }

			if (!isEmpty(elements)) {
				payload.elements = elements
			}

			return payload
		},
		fileId() {
			return Number(this.document.fileId ?? 0)
		},
		settings() {
			const base = pick(this.document.settings, ['signMethod', 'canSign', 'phoneNumber'])
			const user = pick(this.user.settings, ['canRequestSign', 'hasSignatureFile'])
			return {
				...base,
				...user,
			}
		},
		signMethod() {
			return this.settings.signMethod || 'password'
		},
	},
	mounted() {
		this.loading = true

		Promise.all([
			this.loadUser(),
			this.loadSignatures(),
		])
			.catch(console.warn)
			.then(() => {
				this.loading = false
			})
	},
	methods: {
		async loadUser() {
			try {
				this.user = await sigantureService.loadMe()
			} catch (err) {
				onError(err)
			}
		},
		async loadSignatures() {
			try {
				const { elements } = await sigantureService.loadSignatures()
				this.userSignatures = (elements || []).reverse()
			} catch (err) {
				onError(err)
			}
		},
		callSignMethod() {
			if (this.modals[this.signMethod] === undefined) {
				showError(t('libresign', '%s is not a valid sign method', this.signMethod))
				return
			}

			this.modals[this.signMethod] = true
		},
		onModalClose(modal) {
			this.modals[modal] = false
		},
		async signWithPassword(password) {
			this.loading = true

			const payload = { ...this.singPayload, password }

			return this.signDocument(payload)
		},
		async signWithCode(code) {
			this.loading = true

			const payload = { ...this.singPayload, code }

			return this.signDocument(payload)
		},
		async signDocument(payload) {
			this.loading = true
			try {
				const data = await signService.signDocument(payload)
				this.$emit('signed', data)
			} catch (err) {
				onError(err)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<template>
	<div class="document-sign">
		<div class="sign-elements">
			<figure v-for="element in elements" :key="`element-${element.documentElementId}`">
				<img :src="element.url" alt="">
			</figure>
		</div>
		<div>
			<button :disabled="loading" class="button" @click="callSignMethod">
				{{ t('libresign', 'Sign the document.') }}
			</button>
		</div>
		<PasswordManager
			v-if="modals.password"
			v-bind="{ hasPassword }"
			@change="signWithPassword"
			@close="onModalClose('password')" />

		<SMSManager
			v-if="modals.email"
			v-bind="{ settings, fileId }"
			@change="signWithCode"
			@update:phone="val => $emit('update:phone', val)"
			@close="onModalClose('email')" />
	</div>
</template>

<style lang="scss" scoped>
.document-sign {
	display: flex;
	flex-wrap: wrap;
	justify-content: center;
}
</style>
