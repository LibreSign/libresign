<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<form class="external-signing-form" :aria-busy="saving" @submit.prevent="submit">
		<label for="external-signing-instance-name">{{ t('libresign', 'Integration name') }}</label>
		<input
			id="external-signing-instance-name"
			v-model="draft.name"
			:disabled="saving"
			:aria-invalid="Boolean(errors.name)"
			autocomplete="off">
		<p v-if="errors.name" role="alert">
			{{ t('libresign', 'This field is required.') }}
		</p>
		<div v-for="field in driver.fields" :key="field.key" class="external-signing-form__field">
			<label :for="`external-signing-field-${field.key}`">{{ field.label }}</label>
			<p v-if="field.secret && existing?.configuredSecretFields.includes(field.key)">
				{{ t('libresign', 'Secret configured. Leave blank to keep it, or enter a replacement.') }}
			</p>
			<input
				:id="`external-signing-field-${field.key}`"
				:value="field.secret ? draft.secretReplacements[field.key] ?? '' : draft.configuration[field.key] ?? ''"
				:type="field.secret ? 'password' : field.type"
				:required="field.required && !(field.secret && existing?.configuredSecretFields.includes(field.key))"
				:disabled="saving"
				:aria-invalid="Boolean(errors[field.key])"
				:autocomplete="field.secret ? 'new-password' : 'off'"
				@input="onFieldInput(field, $event)">
			<p v-if="errors[field.key]" role="alert">
				{{ getFieldErrorMessage(field.key) }}
			</p>
		</div>
		<p v-if="saveError" role="alert">
			{{ saveError }}
		</p>
		<div class="external-signing-form__actions">
			<NcButton type="submit" variant="primary" :disabled="saving || !driver.available">
				{{ getSaveButtonLabel() }}
			</NcButton>
			<NcButton variant="secondary" :disabled="saving" @click="$emit('cancel')">
				{{ t('libresign', 'Cancel') }}
			</NcButton>
		</div>
	</form>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import { validateExternalSigningDraft } from '../../../services/externalSigningAdmin'
import type { ExternalSigningDriver, ExternalSigningDraft, ExternalSigningField, ExternalSigningInstance } from '../../../services/externalSigningAdmin'

const props = defineProps<{ driver: ExternalSigningDriver, existing?: ExternalSigningInstance, save: (draft: ExternalSigningDraft) => Promise<ExternalSigningInstance> }>()
const emit = defineEmits<{ saved: [instance: ExternalSigningInstance], cancel: [] }>()
const draft = reactive<ExternalSigningDraft>({
	id: props.existing?.id,
	driverId: props.driver.id,
	name: props.existing?.name ?? '',
	configuration: { ...props.existing?.configuration },
	secretReplacements: {},
	revision: props.existing?.revision,
})
const errors = ref<Record<string, string>>({})
const saveError = ref('')
const saving = ref(false)

function getFieldErrorMessage(key: string): string {
	if (errors.value[key] === 'required') {
		// TRANSLATORS Validation message for a required external signing field.
		return t('libresign', 'This field is required.')
	}
	// TRANSLATORS Validation message for an invalid external signing service URL.
	return t('libresign', 'Enter a valid service URL.')
}

function getSaveButtonLabel(): string {
	if (saving.value) {
		// TRANSLATORS Save button label while the integration is being saved.
		return t('libresign', 'Saving…')
	}
	// TRANSLATORS Save button label for an external signing integration.
	return t('libresign', 'Save')
}

function onFieldInput(field: ExternalSigningField, event: Event) {
	const value = (event.target as HTMLInputElement).value
	if (field.secret) {
		draft.secretReplacements[field.key] = value
	} else {
		draft.configuration[field.key] = value
	}
	delete errors.value[field.key]
}

async function submit() {
	if (saving.value) {
		return
	}
	errors.value = validateExternalSigningDraft(props.driver, draft, props.existing)
	if (Object.keys(errors.value).length) {
		return
	}
	saving.value = true
	saveError.value = ''
	try {
		// Never round-trip stored secret values. Empty secret input is omitted.
		const replacements = Object.fromEntries(Object.entries(draft.secretReplacements).filter(([, value]) => value.length > 0))
		const saved = await props.save({ ...draft, secretReplacements: replacements })
		emit('saved', saved)
	} catch {
		// TRANSLATORS Recoverable error when saving an external signing integration failed.
		saveError.value = t('libresign', 'Could not save the integration. Review the configuration and try again.')
	} finally {
		saving.value = false
	}
}
</script>

<style scoped lang="scss">
.external-signing-form { display: grid; gap: 12px; padding: 16px; }
.external-signing-form__field { display: grid; gap: 6px; }
.external-signing-form input { width: 100%; min-width: 0; padding: 8px; border: 1px solid var(--color-border-dark); border-radius: var(--border-radius); }
.external-signing-form__actions { display: flex; gap: 8px; flex-wrap: wrap; }
</style>
