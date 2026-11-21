<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog :name="dialogTitle" :no-close="signing" :buttons="buttons"
		@closing="emit('close', false)">
		<p>
			<!-- TRANSLATORS Confirmation for immediately signing a PDF as the current user without placing visible signature fields. -->
			{{ t('libresign', 'Sign this PDF with your account now, without visible signature fields?') }}
		</p>
		<p>
			<!-- TRANSLATORS Points users to the existing flow when they need signature positions or additional signers. -->
			{{ t('libresign', 'To add signature positions or other signers, use the regular request flow.') }}
		</p>
		<NcNoteCard v-if="errorMessage" type="error">
			{{ errorMessage }}
		</NcNoteCard>
	</NcDialog>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { showSuccess } from '@nextcloud/dialogs'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import { downloadSignedPdf, signPdfDirectly } from '../../services/directSigning.ts'
import type { DirectSignInput } from '../../services/directSigning.ts'

defineOptions({ name: 'DirectSignDialog' })

const props = defineProps<DirectSignInput>()
const emit = defineEmits<{ (event: 'close', signed: boolean): void }>()
const signing = ref(false)
const errorMessage = ref('')
const retryAllowed = ref(true)

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
		// TRANSLATORS Notification shown after the signed PDF has been downloaded.
		showSuccess(t('libresign', 'The signed PDF has been downloaded.'))
		emit('close', true)
	} catch (error) {
		const response = error && typeof error === 'object' && 'response' in error
			? error.response as { status?: number } | undefined
			: undefined
		if (response?.status && response.status < 500 && response.status !== 409) {
			// TRANSLATORS Failure to sign immediately; the normal request workflow remains available.
			errorMessage.value = t('libresign', 'Could not sign this PDF. You can use Sign myself to review the request.')
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
	{
		// TRANSLATORS Confirms immediate signing of the selected PDF.
		label: t('libresign', 'Sign now'),
		variant: 'primary' as const,
		callback: signNow,
		disabled: signing.value || !retryAllowed.value,
	},
])
</script>
