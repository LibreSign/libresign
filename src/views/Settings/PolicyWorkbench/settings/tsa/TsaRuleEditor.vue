<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="tsa-editor">
		<NcCheckboxRadioSwitch type="switch"
			:model-value="enabled"
			@update:modelValue="onToggleEnabled">
			{{ t('libresign', 'Use timestamp server') }}
		</NcCheckboxRadioSwitch>

		<div v-if="enabled" class="tsa-editor__fields">
			<NcTextField :model-value="config.url"
				:label="t('libresign', 'TSA Server URL')"
				:placeholder="t('libresign', 'Enter the timestamp server URL')"
				@update:modelValue="onUrlChange" />

			<NcTextField :model-value="config.policy_oid"
				:label="t('libresign', 'TSA Policy OID')"
				:placeholder="t('libresign', 'Optional')"
				@update:modelValue="onPolicyOidChange" />

			<NcSelect v-model="selectedAuthType"
				:options="authOptions"
				:input-label="t('libresign', 'TSA Authentication')"
				clearable />

			<NcTextField v-if="config.auth_type === 'basic'"
				:model-value="config.username"
				:label="t('libresign', 'Username')"
				:placeholder="t('libresign', 'Username')"
				:helper-text="t('libresign', 'TSA password remains in secure storage and is not changed here.')"
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

const authOptions: AuthOption[] = [
	{ id: 'none', label: t('libresign', 'Without authentication') },
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
