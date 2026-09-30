<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="signer-ip-geolocation-editor">
		<NcCheckboxRadioSwitch
			v-for="option in options"
			:key="option.value"
			class="signer-ip-geolocation-editor__option"
			type="radio"
			:model-value="normalizedMode === option.value"
			name="signer-ip-geolocation-editor"
			@update:modelValue="onChange(option.value, $event)">
			<div class="signer-ip-geolocation-editor__copy">
				<strong>{{ option.label }}</strong>
				<p>{{ option.description }}</p>
			</div>
		</NcCheckboxRadioSwitch>

		<section
			class="signer-ip-geolocation-editor__dependency"
			data-cy="geoip-database-dependency"
			:aria-label="dependencySectionLabel">
			<div class="signer-ip-geolocation-editor__dependency-row">
				<p class="signer-ip-geolocation-editor__dependency-status">
					<strong>{{ dependencyStatusPrefix }}</strong>
					{{ statusLabel || dependencyStatusUnknown }}
				</p>
				<NcButton
					v-if="canConfigureGeoIp"
					variant="tertiary"
					@click="openGeoIpDialog">
					{{ configureButtonLabel }}
				</NcButton>
			</div>

			<NcNoteCard
				v-if="showUnavailableWarning"
				type="warning"
				class="signer-ip-geolocation-editor__dependency-warning">
				<p>{{ unavailableWarning }}</p>
			</NcNoteCard>

			<p
				v-if="!canConfigureGeoIp"
				class="signer-ip-geolocation-editor__dependency-instance-note">
				{{ instanceLevelNote }}
			</p>
		</section>

		<NcDialog
			v-if="canConfigureGeoIp && showGeoIpDialog"
			:name="geoIpDialogName"
			size="normal"
			:can-close="true"
			@closing="closeGeoIpDialog">
			<GeoIpDatabase @updated="onGeoIpUpdated" />
		</NcDialog>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import type { EffectivePolicyValue } from '../../../../../types/index'
import {
	geoIpStatusLabel,
	resolveGeoIpDatabaseStatus,
	type GeoIpConfig,
	type GeoIpDatabaseStatus,
} from '../../../../../helpers/geoipConfig'
import { getGeoIpConfig } from '../../../../../services/geoip'
import GeoIpDatabase from '../../../GeoIpDatabase.vue'
import {
	normalizeSignerIpGeolocationValue,
	resolveSignerIpGeolocationMode,
	type SignerIpGeolocationMode,
} from './model'

defineOptions({
	name: 'SignerIpGeolocationRuleEditor',
})

const props = withDefaults(defineProps<{
	modelValue: EffectivePolicyValue
	editorScope?: 'system' | 'group' | 'user'
}>(), {
	editorScope: 'system',
})

const emit = defineEmits<{
	'update:modelValue': [value: EffectivePolicyValue]
}>()

const options: Array<{ value: SignerIpGeolocationMode, label: string, description: string }> = [
	{
		value: 'disabled',
		// TRANSLATORS Radio option that turns off IP-based approximate location.
		label: t('libresign', 'Disabled'),
		// TRANSLATORS Description for disabled IP geolocation mode.
		description: t('libresign', 'Do not resolve or store an approximate location from the signer IP address.'),
	},
	{
		value: 'enabled',
		// TRANSLATORS Radio option that turns on IP-based approximate location.
		label: t('libresign', 'Enabled'),
		// TRANSLATORS Description explaining IP geolocation uses the signer IP and the local GeoIP database.
		description: t('libresign', 'Use the signer IP address and the locally configured GeoIP database to store an approximate location. This is not a per-signer requester option.'),
	},
]

// TRANSLATORS Accessible name for the GeoIP database dependency block inside the IP geolocation policy editor.
const dependencySectionLabel = t('libresign', 'GeoIP database')
// TRANSLATORS Button that opens the instance GeoIP database configuration dialog.
const configureButtonLabel = t('libresign', 'Configure')
// TRANSLATORS Prefix before the current GeoIP database readiness status.
const dependencyStatusPrefix = t('libresign', 'GeoIP database:')
// TRANSLATORS Fallback when GeoIP status has not loaded yet.
const dependencyStatusUnknown = t('libresign', 'Loading …')
// TRANSLATORS Warning that IP geolocation can stay enabled even when the GeoIP database is not ready.
const unavailableWarning = t('libresign', 'A GeoIP database is required to resolve approximate locations from signer IP addresses. Signing can continue without it, but approximate locations will not be stored until the database is ready.')
// TRANSLATORS Clarifies that GeoIP configuration is instance-wide and cannot be changed from group or user rules.
const instanceLevelNote = t('libresign', 'The GeoIP database is instance configuration and can only be changed by a system administrator.')
// TRANSLATORS Dialog title for the instance GeoIP database configuration form.
const geoIpDialogName = t('libresign', 'GeoIP database')

const geoIpConfig = ref<GeoIpConfig | null>(null)
const showGeoIpDialog = ref(false)

const normalizedMode = computed<SignerIpGeolocationMode | null>(() => resolveSignerIpGeolocationMode(props.modelValue))
const canConfigureGeoIp = computed(() => props.editorScope === 'system')
const status = computed<GeoIpDatabaseStatus | null>(() => resolveGeoIpDatabaseStatus(geoIpConfig.value?.status))
const statusLabel = computed(() => status.value ? geoIpStatusLabel(status.value) : '')
const showUnavailableWarning = computed(() => normalizedMode.value === 'enabled' && status.value !== null && status.value !== 'ready')

function onChange(mode: SignerIpGeolocationMode, selected?: unknown) {
	if (selected === false) {
		return
	}

	emit('update:modelValue', normalizeSignerIpGeolocationValue({ mode }))
}

function openGeoIpDialog() {
	showGeoIpDialog.value = true
}

function closeGeoIpDialog() {
	showGeoIpDialog.value = false
}

function onGeoIpUpdated(config: GeoIpConfig) {
	geoIpConfig.value = config
}

async function loadGeoIpStatus() {
	try {
		geoIpConfig.value = await getGeoIpConfig()
	} catch {
		geoIpConfig.value = null
	}
}

onMounted(() => {
	void loadGeoIpStatus()
})
</script>

<style scoped lang="scss">
.signer-ip-geolocation-editor {
	display: flex;
	flex-direction: column;
	gap: 0.6rem;

	&__copy p {
		margin: 0.2rem 0 0;
		color: var(--color-text-maxcontrast);
	}

	&__dependency {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
		margin-block-start: 0.5rem;
		padding-block-start: 0.75rem;
		border-block-start: 1px solid var(--color-border);
	}

	&__dependency-instance-note,
	&__dependency-status {
		margin: 0;
		color: var(--color-text-maxcontrast);
	}

	&__dependency-status strong {
		color: var(--color-main-text);
		margin-inline-end: 0.35rem;
	}

	&__dependency-row {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 0.5rem;
		justify-content: space-between;
	}

	&__dependency-warning {
		margin: 0;
	}

	:deep(.signer-ip-geolocation-editor__option.checkbox-radio-switch) {
		width: 100%;
	}

	:deep(.signer-ip-geolocation-editor__option .checkbox-radio-switch__content) {
		width: 100%;
		max-width: none;
	}

	:deep(.signer-ip-geolocation-editor__option.checkbox-radio-switch--checked:focus-within .checkbox-radio-switch__content) {
		background-color: transparent;
	}
}
</style>
