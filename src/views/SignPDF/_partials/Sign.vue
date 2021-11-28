<script>
import { get, isEmpty } from 'lodash-es'
import { service as sigantureService } from '../../../domains/signatures'
import { service as signService } from '../../../domains/sign'
import { onError } from '../../../helpers/errors'
import PasswordManager from './ModalPasswordManager.vue'

export default {
	name: 'Sign',
	components: {
		PasswordManager,
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
		callPassword() {
			this.modals.password = true
		},
		onModalClose(modal) {
			this.modals[modal] = false
		},
		async signWithPassword(password) {
			this.loading = true

			const payload = { ...this.singPayload, password }

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
			<button :disabled="loading" class="button" @click="callPassword">
				{{ t('libresign', 'Sign the document.') }}
			</button>
		</div>
		<PasswordManager
			v-if="modals.password"
			v-bind="{ hasPassword }"
			@change="signWithPassword"
			@close="onModalClose('password')" />
	</div>
</template>

<style lang="scss" scoped>
.document-sign {
	display: flex;
	flex-wrap: wrap;
	justify-content: center;
}
</style>
