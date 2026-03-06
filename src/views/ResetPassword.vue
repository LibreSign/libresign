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
		@keydown.enter.prevent="handleEnter"
		@closing="onClose">
		<p>{{ t('libresign', 'Enter new password and then repeat it') }}</p>
		<NcPasswordField v-model="currentPassword"
			ref="currentPasswordField"
			:label="t('libresign', 'Current password')" />
		<NcPasswordField v-model="newPassword"
			ref="newPasswordField"
			:label="t('libresign', 'New password')" />
		<NcPasswordField v-model="rPassword"
			ref="repeatPasswordField"
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

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'

import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'

import { useSignMethodsStore } from '../store/signMethods.js'

defineOptions({
	name: 'ResetPassword',
})

const emit = defineEmits<{
	(e: 'close', success: boolean): void
}>()

const signMethodsStore = useSignMethodsStore()

const currentPasswordField = ref<any | null>(null)
const newPasswordField = ref<any | null>(null)
const repeatPasswordField = ref<any | null>(null)

const newPassword = ref('')
const currentPassword = ref('')
const rPassword = ref('')
const hasLoading = ref(false)

const validNewPassord = computed(() => newPassword.value.length > 3 && newPassword.value === rPassword.value)
const canSave = computed(() => currentPassword.value && validNewPassord.value && !hasLoading.value)

function resetForm() {
	newPassword.value = ''
	currentPassword.value = ''
	rPassword.value = ''
	hasLoading.value = false
}

function getFieldRef(fieldRefName: string) {
	const refs: Record<string, any> = {
		currentPasswordField: currentPasswordField.value,
		newPasswordField: newPasswordField.value,
		repeatPasswordField: repeatPasswordField.value,
	}
	return refs[fieldRefName]
}

function focusField(fieldRefName: string) {
	const field = getFieldRef(fieldRefName)
	if (typeof field?.focus === 'function') {
		field.focus()
		return
	}
	const input = field?.$el?.querySelector?.('input')
	if (typeof input?.focus === 'function') {
		input.focus()
	}
}

function focusFirstInvalidField() {
	if (!currentPassword.value) {
		focusField('currentPasswordField')
		return
	}
	if (!newPassword.value) {
		focusField('newPasswordField')
		return
	}
	if (!rPassword.value || !validNewPassord.value) {
		focusField('repeatPasswordField')
	}
}

function handleEnter() {
	if (canSave.value) {
		send()
		return
	}
	focusFirstInvalidField()
}

function onClose() {
	signMethodsStore.closeModal('resetPassword')
	resetForm()
}

async function send() {
	if (!canSave.value) {
		focusFirstInvalidField()
		return
	}
	hasLoading.value = true
	try {
		const { data } = await axios.patch(generateOcsUrl('/apps/libresign/api/v1/account/pfx'), {
			current: currentPassword.value,
			new: newPassword.value,
		})
		showSuccess(data.ocs.data.message)
		onClose()
		emit('close', true)
	} catch ({ response }: any) {
		if (response?.data?.ocs?.data?.message) {
			showError(response.data.ocs.data.message)
		} else {
			showError(t('libresign', 'Error creating new password, please contact the administrator'))
		}
	}
	hasLoading.value = false
}

defineExpose({
	currentPasswordField,
	newPasswordField,
	repeatPasswordField,
	newPassword,
	currentPassword,
	rPassword,
	hasLoading,
	canSave,
	validNewPassord,
	resetForm,
	focusField,
	focusFirstInvalidField,
	handleEnter,
	onClose,
	send,
})
</script>
