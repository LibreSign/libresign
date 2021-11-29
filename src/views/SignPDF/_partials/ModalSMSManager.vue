<script>
import confirmPassword from '@nextcloud/password-confirmation'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import Content from '../../../Components/Modals/ModalContent.vue'
import { isEmpty } from 'lodash-es'
import { showResponseError } from '../../../helpers/errors'
import { settingsService } from '../../../domains/settings'
import { showError } from '@nextcloud/dialogs'

const sanitizeNumber = val => {
	val = val.replace(/\D/g, '')
	return `+${val}`
}

export default {
	name: 'ModalSMSManager',
	components: {
		Content,
		Modal,
	},
	props: {
		settings: {
			type: Object,
			required: true,
		},
	},
	data: () => ({
		phoneNumber: '',
		loading: false,
	}),
	computed: {
		hasNumber() {
			return !isEmpty(this.settings.phoneNumber)
		},
	},
	watch: {
		'settings.phoneNumber'(val) {
			this.phoneNumber = val || ''
		},
	},
	mounted() {
		this.phoneNumber = this.settings.phoneNumber || ''
	},
	methods: {
		close() {
			this.$emit('close')
		},
		sanitizeNumber() {
			this.phoneNumber = sanitizeNumber(this.phoneNumber)
		},
		async saveNumber() {
			this.loading = true
			this.sanitizeNumber()

			await this.$nextTick()

			try {
				await confirmPassword()
				const { data: { phone }, success } = await settingsService.saveUserNumber(this.phoneNumber)

				this.phoneNumber = phone
				this.$emit('update:phone', phone)

				if (!success) {
					showError(t('libresign', 'Review the entered number.'))
					return
				}
			} catch (err) {
				showResponseError(err)
			} finally {
				this.loading = false
			}
		},
		onChange(val) {
			this.$emit('change', val)

			this.$nextTick(() => {
				this.close()
			})
		},
	},
}
</script>

<template>
	<Modal size="normal" @close="close">
		<Content class="modal-view">
			<template slot="header">
				<h2>{{ t('libresign', 'Sign with your cellphone number.') }}</h2>
				<!-- <p>{{ t('libresign', 'Sign the document.') }}</p> -->
			</template>

			<div v-if="hasNumber">
				{{ phoneNumber }}
			</div>
			<div v-else class="store-number">
				<input v-model="phoneNumber"
					:disabled="loading"
					name="cellphone"
					placeholder="+55 00 0 0000 0000"
					type="tel"
					@change="sanitizeNumber">
				<button :disabled="loading || phoneNumber.length < 10" @click="saveNumber">
					{{ t('libresign', 'Save your number.') }}
				</button>
			</div>
		</Content>
	</Modal>
</template>

<style lang="scss" scoped>
.store-number {
	input {
		font-size: 1.1em;
		width: 100%;
		height: auto !important;
		display: block;
	}
	button {
		display: block;
		margin: 1em auto 0 auto;
	}
}
</style>
