<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<section
		v-if="visible"
		class="external-signing-admin"
		:class="{ 'external-signing-admin--compact': layout === 'compact' }"
		aria-labelledby="external-signing-admin-title"
		data-cy="external-signing-admin">
		<div class="external-signing-admin__entry">
			<div class="external-signing-admin__details">
				<h3 id="external-signing-admin-title">
					{{ t('libresign', 'External signing providers') }}
				</h3>
				<p>{{ t('libresign', 'Manage external signing integrations independently of signing policies.') }}</p>
				<p
					v-if="!adapter"
					id="external-signing-admin-unavailable"
					class="external-signing-admin__status"
					role="status">
					{{ t('libresign', 'Integration management is not connected yet.') }}
				</p>
			</div>
			<div class="external-signing-admin__footer">
				<NcButton
					variant="secondary"
					:disabled="!adapter"
					:aria-describedby="!adapter ? 'external-signing-admin-unavailable' : undefined"
					@click="dialogOpen = true">
					{{ t('libresign', 'Configure integrations') }}
				</NcButton>
			</div>
		</div>
		<NcDialog
			v-if="adapter && dialogOpen"
			:name="t('libresign', 'External signing providers')"
			size="normal"
			@closing="closeDialog">
			<div class="external-signing-admin__dialog">
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
								<p>{{ driverById(instance.driverId)?.name ?? instance.driverId }} · {{ getInstanceStatusLabel(instance) }}</p>
								<p v-if="instance.hasActiveSessions">
									{{ t('libresign', 'An active signing session may restrict changes.') }}
								</p>
							</div>
							<div class="external-signing-admin__actions">
								<NcButton :disabled="busy || !driverById(instance.driverId)?.available" @click="edit(instance)">
									{{ t('libresign', 'Edit') }}
								</NcButton>
								<NcButton :disabled="busy || !driverById(instance.driverId)?.available" @click="toggle(instance)">
									{{ getToggleLabel(instance) }}
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
						<ExternalSigningIntegrationForm
							:key="selectedInstance?.id ?? `new-${selectedDriver.id}`"
							:driver="selectedDriver"
							:existing="selectedInstance"
							:save="adapter.save"
							@saved="onSaved"
							@cancel="resetForm" />
					</template>
					<template v-else>
						<label for="external-signing-driver">{{ t('libresign', 'Provider type') }}</label>
						<select id="external-signing-driver" v-model="selectedDriverId">
							<option value="">
								{{ t('libresign', 'Select a provider') }}
							</option>
							<option
								v-for="driver in drivers"
								:key="driver.id"
								:value="driver.id"
								:disabled="!driver.available">
								{{ driver.name }}{{ driver.available ? '' : ` — ${t('libresign', 'Unavailable')}` }}
							</option>
						</select>
					</template>
				</template>
			</div>
		</NcDialog>
	</section>
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

/** A production adapter is not supplied until the administrative CRUD API is available. */
const props = defineProps<{ adapter?: ExternalSigningAdminAdapter, catalogLoader?: () => Promise<ExternalSigningDriver[]>, layout?: 'cards' | 'compact' }>()
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
function getInstanceStatusLabel(instance: ExternalSigningInstance): string {
	if (!driverById(instance.driverId)?.available) {
		// TRANSLATORS Status of an integration whose signing driver is unavailable.
		return t('libresign', 'Driver unavailable')
	}
	if (instance.enabled) {
		// TRANSLATORS Status of an administratively enabled signing integration.
		return t('libresign', 'Enabled')
	}
	// TRANSLATORS Status of an administratively disabled signing integration.
	return t('libresign', 'Disabled')
}
function getToggleLabel(instance: ExternalSigningInstance): string {
	if (instance.enabled) {
		// TRANSLATORS Action label that disables an external signing integration.
		return t('libresign', 'Disable')
	}
	// TRANSLATORS Action label that enables an external signing integration.
	return t('libresign', 'Enable')
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
.external-signing-admin {
	min-width: 0;
	min-height: 240px;
	padding: 1.25rem;
	border: 1px solid color-mix(in srgb, var(--color-primary-element) 14%, var(--color-border-maxcontrast));
	border-radius: 20px;
	background:
		radial-gradient(circle at top right, color-mix(in srgb, var(--color-primary-element) 14%, transparent), transparent 45%),
		linear-gradient(180deg, color-mix(in srgb, var(--color-main-background) 92%, white), var(--color-main-background));
	text-align: start;

	&--compact {
		min-height: 0;
		padding: 0.9rem 1rem;
		border-radius: 14px;
		border-color: color-mix(in srgb, var(--color-primary-element) 12%, var(--color-border-maxcontrast));
		background: color-mix(in srgb, var(--color-main-background) 94%, white);

		.external-signing-admin__entry {
			flex-direction: row;
			align-items: center;
		}

		.external-signing-admin__footer {
			flex-shrink: 0;
			margin-top: 0;
		}
	}

	&__entry {
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		gap: 1rem;
		height: 100%;
	}

	&__details {
		min-width: 0;
		display: flex;
		flex-direction: column;
		gap: 0.65rem;

		h3,
		p {
			margin: 0;
		}
	}

	&__details h3 {
		font-size: 1.5rem;
		font-weight: 700;
		line-height: 1.35;
	}

	&__details p {
		color: var(--color-text-maxcontrast);
		line-height: 1.4;
		overflow-wrap: anywhere;
	}

	&__status {
		font-size: 0.9rem;
	}

	&__footer {
		margin-top: auto;
		display: flex;
		justify-content: flex-start;
	}

	&__dialog {
		padding: 16px;
		display: grid;
		gap: 12px;
		max-width: 100%;
	}

	&__instances {
		display: grid;
		gap: 8px;
		list-style: none;
		padding: 0;
		margin: 0;

		li {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 16px;
			padding: 16px;
			border: 1px solid var(--color-border);
			border-radius: var(--border-radius-large);
		}
	}

	&__actions {
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
	}
}

@media (max-width: 960px) {
	.external-signing-admin--compact .external-signing-admin__entry {
		flex-direction: column;
		align-items: stretch;
	}
}

@media (max-width: 640px) {
	.external-signing-admin {
		padding: 1rem;

		&__instances li {
			align-items: stretch;
			flex-direction: column;
		}
	}
}
</style>
