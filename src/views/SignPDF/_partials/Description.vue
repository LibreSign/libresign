<!-- eslint-disable vue/no-v-html -->

<script>
import marked from 'marked'
import dompurify from 'dompurify'
import { mapActions, mapGetters } from 'vuex'
import PasswordManager from './ModalPasswordManager.vue'
import Image from '../../../assets/images/application-pdf.png'
import { service as signerService } from '../../../domains/sign'
import { onError } from '../../../helpers/errors'
import { isEmpty } from 'lodash-es'

export default {
	name: 'Description',
	components: {
		PasswordManager,
	},
	props: {
		user: {
			type: Object,
			required: true,
		},
		enable: {
			type: Boolean,
			required: true,
		},
		pdfName: {
			type: String,
			required: true,
		},
		pdfDescription: {
			type: String,
			required: false,
			default: 'Description',
		},
		uuid: {
			type: String,
			required: true,
			default: '',
		},
		elements: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
			image: Image,
			updating: false,
			disableButton: false,
			signaturePath: '2',
			password: '',
			asign: true,
			modal: false,
			havePfx: false,
			showDoc: false,
			viewHeader: false,
			width: window.innerWidth,
		}
	},

	computed: {
		...mapGetters(['error/getError']),
		markedDescription() {
			const description = this.pdfDescription || ''
			return dompurify.sanitize(marked(description), { USE_PROFILES: { html: false } })
		},
	},

	watch: {
		width(newVal, oldVal) {
			if (newVal <= 650) {
				this.showDoc = true
			}
			if (newVal > 650) {
				this.showDoc = false
			}
		},
		user: {
			immediate: true,
			handler(val) {
				this.havePfx = val.settings.hasSignatureFile
			},
		},
	},
	created() {
		this.$nextTick(() => {
			window.addEventListener('resize', this.onResize)
		})
		this.width <= 650
			? this.showDoc = true
			: this.showDoc = false
	},

	methods: {
		...mapActions({
			signDoc: 'sign/SIGN_DOCUMENT',
		}),
		async signDocument() {
			this.updating = true
			this.disableButton = true

			const elements = this.elements
				.map(row => ({
					documentElementId: row.documentElementId,
					profileElementId: row.profileElementId,
				}))

			const payload = { fileId: this.uuid, password: this.password }

			if (!isEmpty(elements)) {
				payload.elements = elements
			}

			try {
				const data = await signerService.signDocument(payload)
				this.$emit('signed', data)
			} catch (err) {
				onError(err)
			} finally {
				this.updating = true
				this.disableButton = true
			}
		},
		changePfx(value) {
			this.havePfx = value
		},
		handleModal(status) {
			this.modal = status
		},
		emitShow() {
			this.$emit('onDocument', true)
			this.showDoc = false
			this.viewHeader = true
		},
		onResize() {
			this.width = window.innerWidth
		},

	},
}
</script>

<template>
	<div class="sidebar-description">
		<div class="sign-elements">
			<figure v-for="element in elements" :key="`element-${element.documentElementId}`">
				<img :src="element.url" alt="">
			</figure>
		</div>
		<form v-if="enable" @submit="(e) => e.preventDefault()">
			<div v-show="signaturePath" class="form-group">
				<label for="password">
					{{ t('libresign', 'Subscription password.') }}
				</label>
				<div class="form-ib-group">
					<input id="password"
						v-model="password"
						v-tooltip.left="{
							content: t('libresign', 'Create your password for signing PDF'),
							trigger: 'false',
							show: !havePfx
						}"
						:disabled="!havePfx"
						type="password">
					<a class="forgot" @click="handleModal(true)">
						{{ havePfx ? t('libresign', 'Forgot your password?') : t('libresign', 'Create password to sign document') }}
					</a>
					<button
						type="button"
						:value=" t('libresign', 'Sign the document.')"
						:class="!updating ? 'primary' : 'primary loading'"
						:disabled="disableButton"
						@click="signDocument">
						{{ t('libresign', 'Sign the document.') }}
					</button>
					<button v-show="showDoc"
						type="button"
						class="button secondary"
						@click="emitShow">
						{{ t('libresign', 'Show Document') }}
					</button>
				</div>
			</div>
		</form>
		<slot />
		<PasswordManager v-if="modal"
			:has-password="havePfx"
			@close="handleModal(false)"
			@change="changePfx" />
	</div>
</template>

<style lang="scss">
.modal-wrapper .modal-container{
	width: 50%;
	height: 100%;
}

.sidebar-description{
	display: flex;
	flex-direction: column;
	align-items: center;

	form{
		input {
			width: 100%;
		}
	}
}

.form-group{
	display: flex;
	flex-direction: column;
	align-items: center;
}

.form-group:first-child{
	padding-bottom: 20px;
}

.form-ib-group{
	display: flex;
	flex-direction: column;
	align-items: center;
}

.forgot {
	text-align: end;
	opacity: .7;
	font-size: 14px;
	cursor: pointer;
	margin-bottom: 20px;
}

.button{
	margin-top: 15px;
}

.sign-elements {
	img {
		max-width: 100%;
	}
}
</style>
