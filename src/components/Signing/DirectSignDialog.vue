<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showDialog" size="normal" :name="dialogTitle" :no-close="signing" :buttons="buttons"
		@closing="emit('close', false)">
		<div class="signing-dialog-copy">
			<p class="document-name">
				<strong><bdi dir="auto">{{ props.name }}</bdi></strong>
			</p>
			<p>
				<!-- TRANSLATORS Explain exactly what the one-click signing option does. -->
				{{ t('libresign', 'Sign this PDF with your account using Click to Sign and download the signed document. No visible signature field will be added.') }}
			</p>
			<p v-if="!errorMessage && !skipConfirmation">
				<!-- TRANSLATORS Explain that there is an alternative action in this dialog for configuring the PDF before signing. -->
				{{ t('libresign', 'Need to place a visible signature or invite someone else? Choose Prepare signing below.') }}
			</p>
		</div>
		<NcCheckboxRadioSwitch v-if="!errorMessage && !skipConfirmation"
			v-model="rememberChoice"
			type="checkbox"
			:disabled="signing">
			<!-- TRANSLATORS Opt out of future confirmation dialogs when explicitly using the immediate PDF signing action. -->
			{{ t('libresign', 'Do not show this confirmation again') }}
		</NcCheckboxRadioSwitch>
		<p v-if="!errorMessage && !skipConfirmation" class="preference-hint">
			<!-- TRANSLATORS Tells users where they can restore the confirmation prompt for immediate signing. -->
			{{ t('libresign', 'This preference is saved only after a successful Sign now action. Restore the confirmation under LibreSign → Preferences → Signature requests.') }}
		</p>
		<NcNoteCard v-if="errorMessage" type="error">
			{{ errorMessage }}
		</NcNoteCard>
	</NcDialog>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, unref } from 'vue'
import { t } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { useUserConfigStore } from '../../store/userconfig.js'

import { downloadSignedPdf, signPdfDirectly } from '../../services/directSigning.ts'
import type { DirectSignInput } from '../../services/directSigning.ts'

defineOptions({ name: 'DirectSignDialog' })

const props = defineProps<DirectSignInput>()
const emit = defineEmits<{ (event: 'close', result: boolean | 'prepare'): void }>()
const signing = ref(false)
const errorMessage = ref('')
const retryAllowed = ref(true)
const userConfigStore = useUserConfigStore()
const skipConfirmation = computed(() => unref(userConfigStore.skip_direct_sign_confirmation) === true)
const showDialog = ref(!skipConfirmation.value)
const rememberChoice = ref(false)
const CONFIRMATION_KEY = 'skip_direct_sign_confirmation'

onMounted(() => {
	if (skipConfirmation.value) {
		void signNow()
	}
})

// TRANSLATORS Dialog title for immediate PDF signing.
const dialogTitle = t('libresign', 'Sign PDF now')

async function signNow() {
	if (signing.value || !retryAllowed.value) {
		return
	}
	signing.value = true
	errorMessage.value = ''
	try {
		const result = await signPdfDirectly(props)
		downloadSignedPdf(result.pdf, result.filename)
		if (rememberChoice.value) {
			try {
				await userConfigStore.update(CONFIRMATION_KEY, true)
			} catch {
				userConfigStore.onUpdate(CONFIRMATION_KEY, false)
				// TRANSLATORS Warning when the PDF was signed but the user preference to skip future confirmations could not be saved.
				showError(t('libresign', 'PDF signed, but your confirmation preference could not be saved.'))
			}
		}
		// TRANSLATORS Notification shown after the signed PDF has been downloaded.
		showSuccess(t('libresign', 'The signed PDF has been downloaded.'))
		emit('close', true)
	} catch (error) {
		showDialog.value = true
		const response = error && typeof error === 'object' && 'response' in error
			? error.response as { status?: number } | undefined
			: undefined
		if (response?.status === 400 || response?.status === 403) {
			// TRANSLATORS Failure to sign immediately; the normal request workflow remains available.
			errorMessage.value = t('libresign', 'Could not sign this PDF. Choose Prepare signing to review the document and its signers.')
		} else {
			retryAllowed.value = false
			// TRANSLATORS Signing may have completed although its network response was lost; warns against duplicate retries.
			errorMessage.value = t('libresign', 'The signing result is unknown. Check your signed documents before trying again.')
		}
	} finally {
		signing.value = false
	}
}

const buttons = computed(() => [
	{
		// TRANSLATORS Closes the direct signing confirmation without signing.
		label: t('libresign', 'Cancel'),
		callback: () => emit('close', false),
		disabled: signing.value,
	},
	...(retryAllowed.value
		? [{
			// TRANSLATORS Choose the normal preparation flow instead of signing immediately.
			label: t('libresign', 'Prepare signing'),
			variant: 'secondary' as const,
			callback: () => emit('close', 'prepare' as const),
			disabled: signing.value,
		}]
		: []),
	{
		// TRANSLATORS Confirms immediate signing of the selected PDF.
		label: t('libresign', 'Sign now'),
		variant: 'primary' as const,
		callback: signNow,
		disabled: signing.value || !retryAllowed.value,
	},
])
</script>

<style scoped>
.signing-dialog-copy {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding-block: 8px;
}
.signing-dialog-copy p {
	margin: 0;
	line-height: 1.5;
}
.document-name {
	word-break: break-word;
}
.preference-hint {
	margin-block: 12px;
	line-height: 1.5;
	color: var(--color-text-maxcontrast);
	font-size: 0.85rem;
}
</style>
