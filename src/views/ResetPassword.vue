/* eslint-disable no-new */
<template>
	<NcDialog v-if="signMethodsStore.modal.resetPassword"
		:name="t('libresign', 'Password reset')"
		@closing="signMethodsStore.closeModal('resetPassword')">
		<p>{{ t('libresign', 'Enter new password and then repeat it') }}</p>
		<div class="container">
			<div class="input-group">
				<label for="new-password">{{ t('libresign', 'Current password') }}</label>
				<NcPasswordField :value.sync="currentPassword" type="password" />
			</div>
			<div class="input-group">
				<label for="new-password">{{ t('libresign', 'New password') }}</label>
				<NcPasswordField :value.sync="newPassword" type="password" />
			</div>
			<div class="input-group">
				<label for="repeat-password">{{ t('libresign', 'Repeat password') }}</label>
				<NcPasswordField :value.sync="rPassword" :has-error="!validNewPassord" type="password" />
			</div>
		</div>
		<template #actions>
			<NcButton :disabled="!canSave"
				:class="hasLoading ? 'btn-load loading primary btn-confirm' : 'primary btn-confirm'"
				@click="send">
				<template #icon>
					<NcLoadingIcon v-if="hasLoading" :size="20" />
				</template>
				{{ t('libresign', 'Confirm') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { useSignMethodsStore } from '../store/signMethods.js'

export default {
	name: 'ResetPassword',
	components: {
		NcDialog,
		NcPasswordField,
		NcButton,
	},
	setup() {
		const signMethodsStore = useSignMethodsStore()
		return { signMethodsStore }
	},
	data() {
		return {
			newPassword: '',
			currentPassword: '',
			rPassword: '',
			hasLoading: false,
		}
	},
	computed: {
		canSave() {
			return this.currentPassword && this.validNewPassord && !this.hasLoading
		},
		validNewPassord() {
			return this.newPassword.length > 3 && this.newPassword === this.rPassword
		},
	},
	methods: {
		async send() {
			this.hasLoading = true
			try {
				const response = await axios.patch(generateOcsUrl('/apps/libresign/api/v1/account/pfx'), {
					current: this.currentPassword,
					new: this.newPassword,
				})
				showSuccess(response.data.message)
				this.hasLoading = false
				this.signMethodsStore.closeModal('resetPassword')
				this.$emit('close', true)
			} catch (err) {
				if (err.response.data.message) {
					showError(err.response.data.message)
				} else {
					showError(t('libresign', 'Error creating new password, please contact the administrator'))
				}
				this.hasLoading = false
			}
		},
	},
}
</script>
<style lang="scss" scoped>
	.container {
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;

		width: 100%;
		max-width: 420px;

		text-align: start;
	}

	.input-group{
		display: flex;
		flex-direction: column;
		margin: 10px;
		width: 100%;
		label:first-child{
			opacity: 0.7;
		}
		input{
			width: 100%;
			max-width: 370px;
		}
	}
</style>
