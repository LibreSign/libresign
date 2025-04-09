<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="signMethodsStore.modal.createPassword"
		:name="t('libresign', 'Password Creation')"
		is-form
		@submit.prevent="send()"
		@closing="signMethodsStore.closeModal('createPassword')">
		<p>{{ t('libresign', 'For security reasons, you must create a password to sign the documents. Enter your new password in the field below.') }}</p>
		<NcPasswordField v-model="password"
			:disabled="hasLoading"
			:label="t('libresign', 'Enter a password')"
			:placeholder="t('libresign', 'Enter a password')" />
		<template #actions>
			<NcButton :disabled="hasLoading"
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
	name: 'CreatePassword',
	components: {
		NcDialog,
		NcPasswordField,
		NcButton,
		NcLoadingIcon,
	},
	setup() {
		const signMethodsStore = useSignMethodsStore()
		return { signMethodsStore }
	},
	data() {
		return {
			hasLoading: false,
			password: '',
		}
	},
	methods: {
		async send() {
			this.hasLoading = true
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/account/signature'), {
				signPassword: this.password,
			})
				.then(() => {
					showSuccess(t('libresign', 'New password to sign documents has been created'))
					this.signMethodsStore.setHasSignatureFile(true)
					this.password = ''
					this.$emit('password:created', true)
					this.signMethodsStore.closeModal('createPassword')
				})
				.catch(({ response }) => {
					this.signMethodsStore.setHasSignatureFile(false)
					if (response.data?.ocs?.data?.message) {
						showError(response.data.ocs.data.message)
					} else {
						showError(t('libresign', 'Error creating new password, please contact the administrator'))
					}
					this.$emit('password:created', false)
				})
			this.hasLoading = false
		},
	},
}
</script>
