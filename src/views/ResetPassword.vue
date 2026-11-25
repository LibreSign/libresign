<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog
		v-if="signMethodsStore.modal.resetPassword"
		:name="t('libresign', 'Password reset')"
		class="container"
		is-form
		@submit.prevent="send()"
		@keydown.enter.prevent="handleEnter"
		@closing="onClose">
		<p>{{ t('libresign', 'Enter new password and then repeat it') }}</p>
		<NcPasswordField
			ref="currentPasswordField"
			v-model="currentPassword"
			:label="t('libresign', 'Current password')" />
		<NcPasswordField
			ref="newPasswordField"
			v-model="newPassword"
			:label="t('libresign', 'New password')" />
		<NcPasswordField
			ref="repeatPasswordField"
			v-model="rPassword"
			:has-error="!validNewPassord"
			:label="t('libresign', 'Repeat password')" />
		<template #actions>
			<NcButton
				:disabled="!canSave"
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

type PasswordFieldRef = {
	focus?: () => void
	$el?: { querySelector?: (selector: string) => { focus?: () => void } | null } | null
} | null

const currentPasswordField = ref<PasswordFieldRef>(null)
const newPasswordField = ref<PasswordFieldRef>(null)
const repeatPasswordField = ref<PasswordFieldRef>(null)

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
	const refs: Record<string, PasswordFieldRef> = {
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
	} catch (error: unknown) {
		const failure = error as { response?: { data?: { ocs?: { data?: { message?: string } } } } }
		const message = failure.response?.data?.ocs?.data?.message
		if (message) {
			showError(message)
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
