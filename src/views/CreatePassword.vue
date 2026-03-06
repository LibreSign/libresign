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
		<NcNoteCard v-if="errorMessage" type="error">
			{{ errorMessage }}
		</NcNoteCard>
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

<script setup lang="ts">
import { t } from '@nextcloud/l10n'

import axios from '@nextcloud/axios'
import { showSuccess } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import { ref } from 'vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'

import { useSignMethodsStore } from '../store/signMethods.js'

type SignMethodsStore = {
	modal: {
		createPassword: boolean
	}
	setHasSignatureFile: (value: boolean) => void
	closeModal: (modalCode: string) => void
}

type CreatePasswordError = {
	response?: {
		data?: {
			ocs?: {
				data?: {
					message?: string
				}
			}
		}
	}
}

defineOptions({
	name: 'CreatePassword',
})

const emit = defineEmits<{
	(e: 'password:created', value: boolean): void
}>()

const signMethodsStore = useSignMethodsStore() as SignMethodsStore
const hasLoading = ref(false)
const password = ref('')
const errorMessage = ref('')

async function send() {
	hasLoading.value = true
	errorMessage.value = ''

	try {
		await axios.post(generateOcsUrl('/apps/libresign/api/v1/account/signature'), {
			signPassword: password.value,
		})
		showSuccess(t('libresign', 'New password to sign documents has been created'))
		signMethodsStore.setHasSignatureFile(true)
		password.value = ''
		emit('password:created', true)
		signMethodsStore.closeModal('createPassword')
	} catch (error) {
		const requestError = error as CreatePasswordError
		signMethodsStore.setHasSignatureFile(false)
		errorMessage.value = requestError.response?.data?.ocs?.data?.message
			|| t('libresign', 'Error creating new password, please contact the administrator')
		emit('password:created', false)
	} finally {
		hasLoading.value = false
	}
}

defineExpose({
	t,
	signMethodsStore,
	hasLoading,
	password,
	errorMessage,
	send,
})
</script>
