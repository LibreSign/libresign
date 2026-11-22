<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="visible" class="external-signing-admin" data-cy="external-signing-admin">
		<div class="external-signing-admin__entry">
			<div>
				<h4>{{ t('libresign', 'External signing providers') }}</h4>
				<p>{{ t('libresign', 'Manage instance-level external signing integrations. These are not signing policies.') }}</p>
			</div>
			<NcButton variant="secondary" @click="dialogOpen = true">
				{{ t('libresign', 'Configure integrations') }}
			</NcButton>
		</div>
		<NcDialog v-if="dialogOpen" :name="t('libresign', 'External signing providers')" size="normal" @closing="closeDialog">
			<div class="external-signing-admin__dialog">
				<p v-if="!adapter" role="status">
					{{ t('libresign', 'Administrative integration management is awaiting backend support.') }}
				</p>
				<template v-else>
					<p v-if="loading" role="status">
						{{ t('libresign', 'Loading integrations…') }}
					</p>
					<p v-else-if="loadError" role="alert">
						{{ t('libresign', 'Could not load the integrations.') }} <NcButton variant="secondary" @click="load">
							{{ t('libresign', 'Retry') }}
						</NcButton>
					</p>
					<template v-else>
						<p v-if="!instances.length" role="status">
							{{ t('libresign', 'No integrations configured.') }}
						</p>
						<ul class="external-signing-admin__instances">
							<li v-for="instance in instances" :key="instance.id">
								<div>
									<strong>{{ instance.name }}</strong>
									<p>{{ driverById(instance.driverId)?.name ?? instance.driverId }} · {{ !driverById(instance.driverId)?.available ? t('libresign', 'Driver unavailable') : instance.enabled ? t('libresign', 'Enabled') : t('libresign', 'Disabled') }}</p>
									<p v-if="instance.hasActiveSessions">
										{{ t('libresign', 'An active signing session may restrict changes.') }}
									</p>
								</div>
								<div class="external-signing-admin__actions">
									<NcButton :disabled="busy || !driverById(instance.driverId)?.available" @click="edit(instance)">
										{{ t('libresign', 'Edit') }}
									</NcButton>
									<NcButton :disabled="busy || !driverById(instance.driverId)?.available" @click="toggle(instance)">
										{{ instance.enabled ? t('libresign', 'Disable') : t('libresign', 'Enable') }}
									</NcButton>
									<NcButton :disabled="busy || instance.hasActiveSessions" @click="remove(instance)">
										{{ t('libresign', 'Remove') }}
									</NcButton>
								</div>
							</li>
						</ul>
						<p v-if="operationError" role="alert">
							{{ operationError }}
						</p>
						<template v-if="selectedDriver">
							<ExternalSigningIntegrationForm :key="selectedInstance?.id ?? `new-${selectedDriver.id}`" :driver="selectedDriver" :existing="selectedInstance" :save="adapter.save" @saved="onSaved" @cancel="resetForm" />
						</template>
						<template v-else>
							<label for="external-signing-driver">{{ t('libresign', 'Provider type') }}</label>
							<select id="external-signing-driver" v-model="selectedDriverId">
								<option value="">
									{{ t('libresign', 'Select a provider') }}
								</option>
								<option v-for="driver in drivers" :key="driver.id" :value="driver.id" :disabled="!driver.available">
									{{ driver.name }}{{ driver.available ? '' : ` — ${t('libresign', 'Unavailable')}` }}
								</option>
							</select>
						</template>
					</template>
				</template>
			</div>
		</NcDialog>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { getCurrentUser } from '@nextcloud/auth'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import { fetchExternalSigningDrivers } from '../../../services/externalSigningAdmin'
import type { ExternalSigningAdminAdapter, ExternalSigningDriver, ExternalSigningInstance } from '../../../services/externalSigningAdmin'
import ExternalSigningIntegrationForm from './ExternalSigningIntegrationForm.vue'

/** Optional adapter is deliberately absent in production until issue #9596 defines APIs. */
const props = defineProps<{ adapter?: ExternalSigningAdminAdapter, catalogLoader?: () => Promise<ExternalSigningDriver[]> }>()
const drivers = ref<ExternalSigningDriver[]>([])
const visible = ref(false)
const dialogOpen = ref(false)
const instances = ref<ExternalSigningInstance[]>([])
const loading = ref(false)
const loadError = ref(false)
const busy = ref(false)
const operationError = ref('')
const selectedDriverId = ref('')
const selectedInstance = ref<ExternalSigningInstance | undefined>()
const selectedDriver = computed(() => driverById(selectedDriverId.value))

function driverById(id: string) {
	return drivers.value.find(driver => driver.id === id)
}
function resetForm() {
	selectedInstance.value = undefined
	selectedDriverId.value = ''
}
function closeDialog() {
	if (!busy.value) {
		dialogOpen.value = false
		resetForm()
	}
}
function edit(instance: ExternalSigningInstance) {
	selectedInstance.value = instance
	selectedDriverId.value = instance.driverId
}
async function load() {
	if (!props.adapter) {
		return
	}
	loading.value = true
	loadError.value = false
	try {
		instances.value = await props.adapter.list()
	} catch {
		loadError.value = true
	} finally {
		loading.value = false
	}
}
async function onSaved(_instance: ExternalSigningInstance) {
	resetForm()
	await load()
}
async function mutate(action: () => Promise<unknown>) {
	if (busy.value) {
		return
	}
	busy.value = true
	operationError.value = ''
	try {
		await action()
		await load()
	} catch {
		// TRANSLATORS Error when an external signing administration operation is rejected or fails.
		operationError.value = t('libresign', 'The operation could not be completed. Refresh and try again.')
	} finally {
		busy.value = false
	}
}
function toggle(instance: ExternalSigningInstance) {
	void mutate(() => props.adapter!.setEnabled(instance, !instance.enabled))
}
function remove(instance: ExternalSigningInstance) {
	// Refuse irreversible actions without an explicit human confirmation.
	if (window.confirm(t('libresign', 'Remove this integration?'))) {
		void mutate(() => props.adapter!.remove(instance))
	}
}
watch(dialogOpen, isOpen => {
	if (isOpen) {
		void load()
	}
})

// A successful administrator-only catalog response is the server-owned experimental gate.
// No fixture or client-side flag can make a hidden production control available.
if (getCurrentUser()?.isAdmin === true) {
	void (props.catalogLoader ?? fetchExternalSigningDrivers)().then(data => {
		drivers.value = data
		visible.value = data.length > 0
	}).catch(() => {
		visible.value = false
	})
}
</script>

<style scoped lang="scss">
.external-signing-admin { margin-block: 16px; }
.external-signing-admin__entry, .external-signing-admin__instances li { display: flex; justify-content: space-between; gap: 16px; align-items: center; padding: 16px; border: 1px solid var(--color-border); border-radius: var(--border-radius-large); }
.external-signing-admin__entry h4 { font-weight: bold; margin: 0 0 6px; }
.external-signing-admin__dialog { padding: 16px; display: grid; gap: 12px; max-width: 100%; }
.external-signing-admin__instances { display: grid; gap: 8px; list-style: none; padding: 0; margin: 0; }
.external-signing-admin__actions { display: flex; flex-wrap: wrap; gap: 6px; }
@media (max-width: 600px) { .external-signing-admin__entry, .external-signing-admin__instances li { align-items: stretch; flex-direction: column; } }
</style>
