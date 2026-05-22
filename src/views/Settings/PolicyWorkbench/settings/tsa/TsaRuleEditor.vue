<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="tsa-editor">
		<NcCheckboxRadioSwitch type="switch"
			:model-value="enabled"
			@update:modelValue="onToggleEnabled">
			<!-- TRANSLATORS Toggle label to enable or disable use of a TSA (Time-Stamp Authority) server during digital signing. -->
			{{ t('libresign', 'Use timestamp server') }}
		</NcCheckboxRadioSwitch>

		<div v-if="enabled" class="tsa-editor__fields">
			<NcTextField :model-value="config.url"
				:label="tsaServerUrlLabel"
				:placeholder="tsaServerUrlPlaceholder"
				@update:modelValue="onUrlChange" />

			<NcTextField :model-value="config.policy_oid"
				:label="tsaPolicyOidLabel"
				:placeholder="optionalPlaceholder"
				@update:modelValue="onPolicyOidChange" />

			<NcSelect v-model="selectedAuthType"
				:options="authOptions"
				:input-label="tsaAuthenticationLabel"
				clearable />

			<NcTextField v-if="config.auth_type === 'basic'"
				:model-value="config.username"
				:label="usernameLabel"
				:placeholder="usernameLabel"
				:helper-text="tsaPasswordSecureStorageHelper"
				@update:modelValue="onUsernameChange" />
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import type { EffectivePolicyValue } from '../../../../../types/index'
import { DEFAULT_TSA_SETTINGS, normalizeTsaSettings, serializeTsaSettings } from './model'
import type { TsaSettingsConfig } from './model'

defineOptions({
	name: 'TsaRuleEditor',
})

type AuthOption = {
	id: TsaSettingsConfig['auth_type']
	label: string
}

const props = defineProps<{
	modelValue: EffectivePolicyValue
}>()

const emit = defineEmits<{
	'update:modelValue': [value: string]
}>()

const config = computed(() => normalizeTsaSettings(props.modelValue))
const enabled = computed(() => config.value.url.length > 0)

// TRANSLATORS Field label for TSA endpoint URL. TSA means Time-Stamp Authority.
const tsaServerUrlLabel = t('libresign', 'TSA Server URL')
// TRANSLATORS Placeholder asking for the full TSA (Time-Stamp Authority) service URL used to request trusted timestamps.
const tsaServerUrlPlaceholder = t('libresign', 'Enter the timestamp server URL')
// TRANSLATORS Field label for optional TSA (Time-Stamp Authority) policy OID (Object Identifier) required by some timestamp authorities.
const tsaPolicyOidLabel = t('libresign', 'TSA Policy OID')
// TRANSLATORS Placeholder indicating this TSA (Time-Stamp Authority) policy OID field can be left empty.
const optionalPlaceholder = t('libresign', 'Optional')
// TRANSLATORS Select label for choosing authentication type when connecting to TSA (Time-Stamp Authority) service.
const tsaAuthenticationLabel = t('libresign', 'TSA Authentication')
// TRANSLATORS Username field label for TSA (Time-Stamp Authority) basic authentication credentials.
const usernameLabel = t('libresign', 'Username')
// TRANSLATORS Helper text explaining that the TSA (Time-Stamp Authority) password is stored securely and is not edited in this form.
const tsaPasswordSecureStorageHelper = t('libresign', 'TSA password remains in secure storage and is not changed here.')

const authOptions: AuthOption[] = [
	// TRANSLATORS Authentication option meaning no credentials are sent to the TSA (Time-Stamp Authority) server.
	{ id: 'none', label: t('libresign', 'Without authentication') },
	// TRANSLATORS Authentication option meaning TSA (Time-Stamp Authority) requests use HTTP Basic auth with username and password.
	{ id: 'basic', label: t('libresign', 'Username / Password') },
]

const selectedAuthType = computed<AuthOption>({
	get() {
		return authOptions.find(option => option.id === config.value.auth_type) ?? authOptions[0]
	},
	set(value) {
		const nextAuthType = value?.id ?? 'none'
		emitConfig({
			auth_type: nextAuthType,
			username: nextAuthType === 'basic' ? config.value.username : '',
		})
	},
})

function onToggleEnabled(nextEnabled: boolean): void {
	if (!nextEnabled) {
		emit('update:modelValue', serializeTsaSettings(DEFAULT_TSA_SETTINGS))
		return
	}

	emitConfig({
		url: config.value.url || 'https://freetsa.org/tsr',
	})
}

function onUrlChange(value: string | number): void {
	emitConfig({ url: String(value) })
}

function onPolicyOidChange(value: string | number): void {
	emitConfig({ policy_oid: String(value) })
}

function onUsernameChange(value: string | number): void {
	emitConfig({ username: String(value) })
}

function emitConfig(partial: Partial<TsaSettingsConfig>): void {
	const nextConfig: TsaSettingsConfig = {
		...config.value,
		...partial,
	}

	emit('update:modelValue', serializeTsaSettings(nextConfig))
}
</script>

<style scoped lang="scss">
.tsa-editor {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
}

.tsa-editor__fields {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
	margin-left: 0.25rem;
}
</style>
