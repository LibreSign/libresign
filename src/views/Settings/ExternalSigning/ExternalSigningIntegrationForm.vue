<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<form class="external-signing-form" :aria-busy="saving" @submit.prevent="submit">
		<p v-if="!driver.available" role="status">
			{{ t('libresign', 'The signing driver is unavailable. You can save configuration, but cannot enable signing yet.') }}
		</p>
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
				:disabled="saving || Boolean(secretClears[field.key])"
				:aria-invalid="Boolean(errors[field.key])"
				:autocomplete="field.secret ? 'new-password' : 'off'"
				@input="onFieldInput(field, $event)">
			<div
				v-if="field.secret && existing?.configuredSecretFields.includes(field.key) && !field.required"
				class="external-signing-form__secret-clear">
				<input
					:id="`external-signing-clear-${field.key}`"
					type="checkbox"
					:checked="Boolean(secretClears[field.key])"
					:disabled="saving"
					@change="onSecretClear(field.key, $event)">
				<label :for="`external-signing-clear-${field.key}`">
					{{ t('libresign', 'Remove stored secret') }}
				</label>
			</div>
			<p v-if="errors[field.key]" role="alert">
				{{ getFieldErrorMessage(field.key) }}
			</p>
		</div>
		<p v-if="saveError" role="alert">
			{{ saveError }}
		</p>
		<div class="external-signing-form__actions">
			<NcButton type="submit" variant="primary" :disabled="saving">
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
import { ExternalSigningAdminError } from '../../../services/externalSigningAdminOcs'
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
const secretClears = reactive<Record<string, boolean>>({})
const saveError = ref('')
const saving = ref(false)

function getFieldErrorMessage(key: string): string {
	if (errors.value[key] === 'required') {
		// TRANSLATORS Validation message for a required external signing field.
		return t('libresign', 'This field is required.')
	}
	if (props.driver.fields.find(field => field.key === key)?.secret) {
		// TRANSLATORS Validation message for an external signing secret longer than the permitted limit.
		return t('libresign', 'The secret exceeds the allowed length.')
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
		secretClears[field.key] = false
	} else {
		draft.configuration[field.key] = value
	}
	delete errors.value[field.key]
}

function onSecretClear(key: string, event: Event) {
	const shouldClear = (event.target as HTMLInputElement).checked
	secretClears[key] = shouldClear
	// Explicit null removes an optional secret; an omitted key preserves it.
	draft.secretReplacements[key] = shouldClear ? null : ''
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
		const replacements = Object.fromEntries(Object.entries(draft.secretReplacements).filter(([, value]) => value === null || value.length > 0))
		const saved = await props.save({ ...draft, secretReplacements: replacements })
		emit('saved', saved)
	} catch (error) {
		if (error instanceof ExternalSigningAdminError && error.status === 409) {
			// TRANSLATORS Another administrator changed the external integration while it was being edited.
			saveError.value = t('libresign', 'This integration was changed by another administrator. Refresh before saving.')
		} else {
			// TRANSLATORS Recoverable error when saving an external signing integration failed.
			saveError.value = t('libresign', 'Could not save the integration. Review the configuration and try again.')
		}
	} finally {
		saving.value = false
	}
}
</script>

<style scoped lang="scss">
.external-signing-form { box-sizing: border-box; display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; width: 100%; min-width: 0; padding: 16px; }

.external-signing-form__field { display: grid; gap: 6px; }

.external-signing-form input { width: 100%; min-width: 0; padding: 8px; border: 1px solid var(--color-border-dark); border-radius: var(--border-radius); }

.external-signing-form__actions { display: flex; gap: 8px; flex-wrap: wrap; }

.external-signing-form__secret-clear { display: flex; align-items: center; gap: 8px; }

.external-signing-form__secret-clear input { width: auto; }
</style>
