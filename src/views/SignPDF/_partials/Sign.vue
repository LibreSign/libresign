<template>
	<div class="document-sign">
		<div class="sign-elements">
			<figure v-for="element in elements" :key="`element-${element.documentElementId}`">
				<PreviewSignature :src="element.url" />
			</figure>
		</div>
		<div v-if="ableToSign" class="button-wrapper">
			<NcButton
				:wide="true"
				:disabled="loading"
				@click="callSignMethod"
				type="primary">
				{{ t('libresign', 'Sign the document.') }}
			</NcButton>
		</div>
		<div v-else-if="!loading" class="button-wrapper">
			<div v-if="needPassword && !hasPassword">
				<p>
					{{ t('libresign', 'Please define your sign password') }}
				</p>
				<NcButton
					:wide="true"
					:disabled="loading"
					@click="callPassword"
					type="primary">
					{{ t('libresign', 'Define a password and sign the document.') }}
				</NcButton>
			</div>
			<div v-else-if="needSignature && !hasSignatures" class="no-signature-warning">
				<p>
					{{ t('libresign', 'You do not have any signature defined.') }}
				</p>

				<NcButton
					:wide="true"
					:disabled="loading"
					@click="callCreateSignature"
					type="primary">
					{{ t('libresign', 'Define your signature.') }}
				</NcButton>
			</div>
			<div v-else>
				<p>
					{{ t('libresign', 'Unable to sign.') }}
				</p>
			</div>
		</div>
		<Draw v-if="modals.createSignature"
			:draw-editor="true"
			:text-editor="true"
			:file-editor="true"
			@save="saveSignature"
			@close="onModalClose('createSignature')" />
		<PasswordManager v-if="modals.password"
			v-bind="{ hasPassword, signMethod }"
			@change="signWithPassword"
			@create="onPasswordCreate"
			@close="onModalClose('password')" />

		<SMSManager v-if="modals.sms"
			v-bind="{ settings, fileId }"
			@change="signWithCode"
			@update:phone="val => $emit('update:phone', val)"
			@close="onModalClose('sms')" />

		<EmailManager v-if="modals.email"
			v-bind="{ settings, fileId }"
			@change="signWithCode"
			@close="onModalClose('email')" />
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import { get, isEmpty, pick } from 'lodash-es'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { service as sigantureService } from '../../../domains/signatures/index.js'
import { service as signService } from '../../../domains/sign/index.js'
import { onError } from '../../../helpers/errors.js'
import PasswordManager from './ModalPasswordManager.vue'
import SMSManager from './ModalSMSManager.vue'
import EmailManager from './ModalEmailManager.vue'
import PreviewSignature from '../../../Components/PreviewSignature/PreviewSignature.vue'
import Draw from '../../../Components/Draw/Draw.vue'
import { loadState } from '@nextcloud/initial-state'

const SIGN_METHODS = Object.freeze({
	PASSWORD: 'PasswordManager',
	EMAIL: 'EmailManager',
	SMS: 'SMSManager',
})

export default {
	name: 'Sign',
	SIGN_METHODS,
	components: {
		NcButton,
		PasswordManager,
		SMSManager,
		EmailManager,
		PreviewSignature,
		Draw,
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
		docType: {
			type: String,
			required: false,
			default: 'default',
		},
	},
	data: () => ({
		loading: true,
		modals: {
			password: false,
			email: false,
			sms: false,
			createSignature: false,
		},
		user: {
			account: { uid: '', displayName: '', emailAddress: '' },
			settings: { canRequestSign: false },
		},
		userSignatures: [],
		createPassword: false,
		hasPassword: loadState('libresign', 'config', {})?.hasSignatureFile
	}),
	computed: {
		signer() {
			return this.document?.signers.find(row => row.me) || {}
		},
		visibleElements() {
			const { signRequestId } = this.signer

			if (!signRequestId) {
				return []
			}

			return (this.document?.visibleElements || [])
				.filter(row => row.signRequestId === this.signer.signRequestId)
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
		needPassword() {
			return this.signMethod === 'password'
		},
		ableToSign() {
			if (this.needPassword && !this.hasPassword) {
				return false
			}

			if (this.needSignature && !this.hasSignatures) {
				return false
			}

			return true
		},
		singPayload() {
			const elements = this.elements
				.map(row => ({
					documentElementId: row.documentElementId,
					profileElementId: row.profileElementId,
				}))

			const fileId = this.docType === 'document-validate'
				? this.fileId
				: this.uuid

			const payload = { fileId }

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
			const user = pick(this.user.settings, ['canRequestSign'])

			return {
				...base,
				...user,
				email: this.email,
			}
		},
		signMethod() {
			return this.settings.signMethod || 'password'
		},
		email() {
			return this.user?.account?.emailAddress || 'unknown'
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
			}
		},
		async loadSignatures() {
			try {
				const { elements } = await sigantureService.loadSignatures()
				this.userSignatures = (elements || []).reverse()
			} catch (err) {
			}
		},
		async saveSignature(value) {
			try {
				const res = await axios.post(generateOcsUrl('/apps/libresign/api/v1/account/signature/elements'), {
					elements: [
						{
							file: {
								base64: value,
							},
							type: 'signature',
						},
					],
				})
				await this.loadSignatures()
				showSuccess(res.data.message)
			} catch (err) {
				onError(err)
			}
			this.modals.createSignature = false
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
		onPasswordCreate() {
			this.hasPassword = true
		},
		callPassword() {
			this.modals.password = true
		},
		callCreateSignature() {
			this.modals.createSignature = true
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
	},
}
</script>

<style lang="scss" scoped>
.no-signature-warning {
	margin-top: 1em;
}

.button-wrapper {
	padding: calc(var(--default-grid-baseline, 4px)*2);
}

.sign-elements {
	img {
		max-width: 100%;
	}
}
</style>
