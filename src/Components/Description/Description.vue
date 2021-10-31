<!-- eslint-disable vue/no-v-html -->
<!--
- @copyright Copyright (c) 2021 Lyseon Tech <contato@lt.coop.br>
-
- @author Lyseon Tech <contato@lt.coop.br>
- @author Vinicios Gomes <viniciusgomesvaian@gmail.com>
-
- @license GNU AGPL version 3 or any later version
-
- This program is free software: you can redistribute it and/or modify
- it under the terms of the GNU Affero General Public License as
- published by the Free Software Foundation, either version 3 of the
- License, or (at your option) any later version.
-
- This program is distributed in the hope that it will be useful,
- but WITHOUT ANY WARRANTY; without even the implied warranty of
- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
- GNU Affero General Public License for more details.
-
- You should have received a copy of the GNU Affero General Public License
- along with this program.  If not, see <http://www.gnu.org/licenses/>.
-
-->

<template>
	<div class="container-desc">
		<header v-show="!viewHeader">
			<img :src="image">
			<p>{{ t('libresign', pdfName) }}</p>
			<span v-html="markedDescription" />
		</header>
		<div id="body">
			<form @submit="(e) => e.preventDefault()">
				<div v-show="signaturePath" class="form-group">
					<label for="password">{{
						t('libresign', 'Subscription password.')
					}}</label>
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
			<Modal v-if="modal"
				size="normal"
				@close="handleModal(false)">
				<ResetPassword v-if="havePfx" class="modal-dialog" @close="handleModal(false)" />
				<CreatePassword v-if="!havePfx"
					@changePfx="changePfx"
					@close="handleModal(false)" />
			</Modal>
		</div>
	</div>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import ResetPassword from '../../views/ResetPassword.vue'
import CreatePassword from '../../views/CreatePassword.vue'
import axios from '@nextcloud/axios'
import Image from '../../assets/images/application-pdf.png'
import { generateUrl } from '@nextcloud/router'
import marked from 'marked'
import dompurify from 'dompurify'
import { mapActions, mapGetters } from 'vuex'

export default {
	name: 'Description',
	components: {
		Modal,
		ResetPassword,
		CreatePassword,
	},
	props: {
		pdfName: {
			type: String,
			required: true,
			default: 'PDF Name',
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
	},
	created() {
		this.$nextTick(() => {
			window.addEventListener('resize', this.onResize)
		})
		this.width <= 650
			? this.showDoc = true
			: this.showDoc = false

		this.getMe()
	},

	methods: {
		...mapActions({
			signDoc: 'sign/SIGN_DOCUMENT',
		}),
		async signDocument() {
			this.updating = true
			this.disableButton = true

			this.signDoc({ fileId: this.uuid, password: this.password })

			if (this['error/getError'].length > 0) {
				this.updating = false
				this.disableButton = false
			} else {
				this.updating = true
				this.disableButton = true
			}
		},
		changePfx(value) {
			this.havePfx = value
		},
		async getMe() {
			const response = await axios.get(generateUrl('/apps/libresign/api/0.1/account/me'))
			this.havePfx = response.data.settings.hasSignatureFile
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

<style lang="scss">
@import './styles';
</style>
