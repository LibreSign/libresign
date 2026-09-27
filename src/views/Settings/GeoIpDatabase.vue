<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection
		:name="sectionName"
		:description="sectionDescription">
		<NcNoteCard v-if="status" :type="statusTone">
			<p><strong>{{ statusLabel }}</strong></p>
			<p>{{ statusDescription }}</p>
		</NcNoteCard>

		<NcTextField
			v-model="draftPath"
			class="geoip-database__path"
			:label="pathLabel"
			:placeholder="pathPlaceholder"
			:disabled="busy"
			@keyup.enter="savePath" />

		<div class="geoip-database__actions">
			<NcButton
				variant="primary"
				:disabled="busy || !hasPathChanges"
				@click="savePath">
				<template #icon>
					<NcLoadingIcon v-if="busy" :size="20" />
				</template>
				<!-- TRANSLATORS Button label to save the GeoIP database path. -->
				{{ t('libresign', 'Save') }}
			</NcButton>
			<NcButton
				variant="tertiary"
				:disabled="busy || !canClear"
				@click="clearPath">
				<!-- TRANSLATORS Button label to clear the configured GeoIP database path. -->
				{{ t('libresign', 'Clear path') }}
			</NcButton>
		</div>

		<dl v-if="hasMetadata" class="geoip-database__meta">
			<div v-if="config?.databaseType" class="geoip-database__field">
				<!-- TRANSLATORS Label for the detected GeoIP database product type. -->
				<dt>{{ t('libresign', 'Database type:') }}</dt>
				<dd>{{ config.databaseType }}</dd>
			</div>
			<div v-if="buildTime" class="geoip-database__field">
				<!-- TRANSLATORS Label for the GeoIP database build time reported by the backend. -->
				<dt>{{ t('libresign', 'Database build time:') }}</dt>
				<dd>{{ buildTime }}</dd>
			</div>
			<div v-if="modifiedAt" class="geoip-database__field">
				<!-- TRANSLATORS Label for the GeoIP database file modification time. -->
				<dt>{{ t('libresign', 'File modified:') }}</dt>
				<dd>{{ modifiedAt }}</dd>
			</div>
		</dl>
	</NcSettingsSection>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import {
	formatGeoIpBuildTime,
	formatGeoIpModifiedAt,
	geoIpStatusDescription,
	geoIpStatusLabel,
	geoIpStatusTone,
	resolveGeoIpDatabaseStatus,
	type GeoIpConfig,
	type GeoIpDatabaseStatus,
} from '../../helpers/geoipConfig'
import { getGeoIpConfig, saveGeoIpConfig } from '../../services/geoip'

defineOptions({
	name: 'GeoIpDatabase',
})

// TRANSLATORS Admin settings section title for the local GeoIP City database path.
const sectionName = t('libresign', 'GeoIP database')
// TRANSLATORS Admin settings section description for configuring a local MaxMind City database.
const sectionDescription = t('libresign', 'Configure the local MaxMind City database used for approximate IP-based signer location. This is instance configuration, not a group or user policy.')
// TRANSLATORS Label for the absolute filesystem path of the GeoIP City database.
const pathLabel = t('libresign', 'Database path')
// TRANSLATORS Placeholder showing an example absolute GeoIP database path.
const pathPlaceholder = t('libresign', '/var/lib/libresign/GeoLite2-City.mmdb')
const config = ref<GeoIpConfig | null>(null)
const draftPath = ref('')
const busy = ref(false)

const status = computed<GeoIpDatabaseStatus | null>(() => resolveGeoIpDatabaseStatus(config.value?.status))
const statusLabel = computed(() => status.value ? geoIpStatusLabel(status.value) : '')
const statusDescription = computed(() => status.value ? geoIpStatusDescription(status.value) : '')
const statusTone = computed(() => status.value ? geoIpStatusTone(status.value) : 'info')
const configuredPath = computed(() => config.value?.path ?? '')
const hasPathChanges = computed(() => draftPath.value.trim() !== configuredPath.value)
const canClear = computed(() => configuredPath.value !== '' || draftPath.value.trim() !== '')
const buildTime = computed(() => formatGeoIpBuildTime(config.value?.buildEpoch))
const modifiedAt = computed(() => formatGeoIpModifiedAt(config.value?.modifiedAt))
const hasMetadata = computed(() => Boolean(config.value?.databaseType || buildTime.value || modifiedAt.value))

function applyConfig(next: GeoIpConfig) {
	config.value = next
	draftPath.value = next.path ?? ''
}

async function loadConfig() {
	busy.value = true
	try {
		applyConfig(await getGeoIpConfig())
	} catch {
		// TRANSLATORS Error shown when the GeoIP configuration cannot be loaded.
		showError(t('libresign', 'Could not load the GeoIP database configuration.'))
	} finally {
		busy.value = false
	}
}

async function persistPath(path: string) {
	busy.value = true
	try {
		applyConfig(await saveGeoIpConfig(path))
		// TRANSLATORS Toast confirming that the GeoIP database path was saved.
		showSuccess(t('libresign', 'GeoIP database path saved'))
	} catch {
		// TRANSLATORS Error shown when the GeoIP database path cannot be saved.
		showError(t('libresign', 'Could not save the GeoIP database path.'))
	} finally {
		busy.value = false
	}
}

function savePath() {
	void persistPath(draftPath.value.trim())
}

function clearPath() {
	draftPath.value = ''
	void persistPath('')
}

onMounted(() => {
	void loadConfig()
})

defineExpose({
	config,
	draftPath,
	busy,
	status,
	statusLabel,
	statusDescription,
	statusTone,
	hasPathChanges,
	canClear,
	buildTime,
	modifiedAt,
	hasMetadata,
	loadConfig,
	savePath,
	clearPath,
})
</script>

<style scoped lang="scss">
.geoip-database__path {
	max-width: 40rem;
}

.geoip-database__actions {
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
	margin-block-start: 0.75rem;
}

.geoip-database__meta {
	display: flex;
	flex-direction: column;
	gap: 0.25rem;
	margin: 1rem 0 0;
}

.geoip-database__field {
	display: flex;
	flex-wrap: wrap;
	gap: 0.4rem;
	line-height: 1.5;

	dt {
		font-weight: bold;
		margin: 0;
	}

	dd {
		margin: 0;
		word-break: break-word;
	}
}
</style>
