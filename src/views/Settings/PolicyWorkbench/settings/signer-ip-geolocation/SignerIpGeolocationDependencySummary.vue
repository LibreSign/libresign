<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section
		class="signer-ip-geolocation-dependency"
		data-cy="geoip-database-dependency"
		:aria-label="dependencySectionLabel">
		<div v-if="canConfigure" class="signer-ip-geolocation-dependency__row">
			<p class="signer-ip-geolocation-dependency__status">
				<strong>{{ dependencyStatusPrefix }}</strong>
				{{ statusLabel || dependencyStatusUnknown }}
			</p>
			<NcButton
				v-if="canConfigure"
				variant="tertiary"
				size="small"
				class="signer-ip-geolocation-dependency__configure"
				@click="emit('configure')">
				{{ configureButtonLabel }}
			</NcButton>
		</div>

		<NcNoteCard
			v-if="showUnavailableWarning"
			type="warning"
			class="signer-ip-geolocation-dependency__warning">
			<p>{{ unavailableWarning }}</p>
		</NcNoteCard>

		<p
			v-if="!canConfigure"
			class="signer-ip-geolocation-dependency__instance-note">
			{{ instanceLevelNote }}
		</p>
	</section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import {
	geoIpStatusLabel,
	resolveGeoIpDatabaseStatus,
	type GeoIpConfig,
	type GeoIpDatabaseStatus,
} from '../../../../../helpers/geoipConfig'
import { getGeoIpConfig } from '../../../../../services/geoip'
import { usePoliciesStore } from '../../../../../store/policies'
import { resolveSignerIpGeolocationMode } from './model'

defineOptions({
	name: 'SignerIpGeolocationDependencySummary',
})

const props = withDefaults(defineProps<{
	canConfigure?: boolean
}>(), {
	canConfigure: true,
})

const emit = defineEmits<{
	configure: []
}>()

// TRANSLATORS Accessible name for the GeoIP database dependency block in the IP geolocation setting dialog.
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

const policiesStore = usePoliciesStore()
const geoIpConfig = ref<GeoIpConfig | null>(null)

const canConfigure = computed(() => props.canConfigure)
const status = computed<GeoIpDatabaseStatus | null>(() => resolveGeoIpDatabaseStatus(geoIpConfig.value?.status))
const statusLabel = computed(() => status.value ? geoIpStatusLabel(status.value) : '')
const policyMode = computed(() => resolveSignerIpGeolocationMode(policiesStore.getPolicy('signer_ip_geolocation')?.effectiveValue))
const showUnavailableWarning = computed(() => policyMode.value === 'enabled' && status.value !== null && status.value !== 'ready')

function applyGeoIpConfig(config: GeoIpConfig) {
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
	if (canConfigure.value) {
		void loadGeoIpStatus()
	}
})

defineExpose({
	applyGeoIpConfig,
})
</script>

<style scoped lang="scss">
.signer-ip-geolocation-dependency {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
	margin: 0 0 0.55rem;
}

.signer-ip-geolocation-dependency__row {
	display: inline-flex;
	align-items: center;
	gap: 0.35rem;
	flex-wrap: wrap;
	font-size: 0.9rem;
	line-height: 1.3;
}

.signer-ip-geolocation-dependency__status {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.signer-ip-geolocation-dependency__status strong {
	color: var(--color-main-text);
	margin-inline-end: 0.35rem;
}

.signer-ip-geolocation-dependency__configure {
	margin-inline-start: 0.35rem;

	:deep(.button-vue) {
		min-height: auto;
		padding: 0.05rem 0.35rem;
		font-size: 0.84rem;
		font-weight: 600;
	}
}

.signer-ip-geolocation-dependency__warning {
	margin: 0;
}

.signer-ip-geolocation-dependency__instance-note {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}
</style>
