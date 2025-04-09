<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
/* eslint-disable no-new */
<template>
	<NcDialog v-if="signMethodsStore.modal.resetPassword"
		:name="t('libresign', 'Password reset')"
		class="container"
		is-form
		@submit.prevent="send()"
		@closing="signMethodsStore.closeModal('resetPassword')">
		<p>{{ t('libresign', 'Enter new password and then repeat it') }}</p>
		<NcPasswordField v-model="currentPassword"
			:label="t('libresign', 'Current password')" />
		<NcPasswordField v-model="newPassword"
			:label="t('libresign', 'New password')" />
		<NcPasswordField v-model="rPassword"
			:has-error="!validNewPassord"
			:label="t('libresign', 'Repeat password')" />
		<template #actions>
			<NcButton :disabled="!canSave"
				:class="hasLoading ? 'btn-load loading primary btn-confirm' : 'primary btn-confirm'"
				type="submit"
				variant="primary"
				@click="send()">
				<template #icon>
					<NcLoadingIcon v-if="hasLoading" :size="20" />
				</template>
				{{ t('libresign', 'Confirm') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'

import { useSignMethodsStore } from '../store/signMethods.js'

export default {
	name: 'ResetPassword',
	components: {
		NcDialog,
		NcPasswordField,
		NcLoadingIcon,
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
			await axios.patch(generateOcsUrl('/apps/libresign/api/v1/account/pfx'), {
				current: this.currentPassword,
				new: this.newPassword,
			})
				.then(({ data }) => {
					showSuccess(data.ocs.data.message)
					this.hasLoading = false
					this.signMethodsStore.closeModal('resetPassword')
					this.$emit('close', true)
				})
				.catch(({ response }) => {
					if (response.data.ocs.data.message) {
						showError(response.data.ocs.data.message)
					} else {
						showError(t('libresign', 'Error creating new password, please contact the administrator'))
					}
				})
			this.hasLoading = false
		},
	},
}
</script>
