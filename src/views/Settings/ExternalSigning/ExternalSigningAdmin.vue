<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<section
		v-if="visible"
		class="external-signing-admin"
		:class="{ 'external-signing-admin--compact': layout === 'compact', 'external-signing-admin--embedded': embedded }"
		:aria-labelledby="embedded ? undefined : 'external-signing-admin-title'"
		:aria-label="embedded ? t('libresign', 'External signing providers') : undefined"
		data-cy="external-signing-admin">
		<div class="external-signing-admin__entry">
			<div v-if="!embedded" class="external-signing-admin__details">
				<h3 id="external-signing-admin-title">
					{{ t('libresign', 'External signing providers') }}
				</h3>
				<p>{{ t('libresign', 'Manage CSC connections for this policy rule.') }}</p>
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
				<div v-else-if="loadError">
					<p role="alert">
						{{ loadErrorMessage }}
					</p>
					<NcButton variant="secondary" @click="load">
						{{ t('libresign', 'Retry') }}
					</NcButton>
				</div>
				<template v-else>
					<p v-if="!instances.length" role="status">
						{{ t('libresign', 'No integrations configured.') }}
					</p>
					<ul class="external-signing-admin__instances">
						<li v-for="instance in instances" :key="instance.id">
							<div>
								<strong>{{ instance.name }}</strong>
								<p>{{ driverById(instance.driverId)?.name ?? instance.driverId }} · {{ getInstanceStatusLabel(instance) }} · {{ getDriverStatusLabel(instance) }}</p>
							</div>
							<div class="external-signing-admin__actions">
								<NcActions :aria-label="getActionsLabel(instance)">
									<NcActionButton :close-after-click="true" :disabled="busy" @click="edit(instance)">
										<template #icon>
											<NcIconSvgWrapper :path="mdiPencil" :size="20" />
										</template>
										{{ t('libresign', 'Edit') }}
									</NcActionButton>
									<NcActionButton :close-after-click="true" :disabled="busy || (!instance.enabled && !isDriverAvailable(instance))" @click="toggle(instance)">
										<template #icon>
											<NcIconSvgWrapper :path="instance.enabled ? mdiPowerOff : mdiPower" :size="20" />
										</template>
										{{ getToggleLabel(instance) }}
									</NcActionButton>
									<NcActionButton :close-after-click="true" :disabled="busy" @click="remove(instance)">
										<template #icon>
											<NcIconSvgWrapper :path="mdiDelete" :size="20" />
										</template>
										{{ t('libresign', 'Remove') }}
									</NcActionButton>
								</NcActions>
							</div>
						</li>
					</ul>
					<p v-if="operationError" role="alert">
						{{ operationError }}
					</p>
					<p v-if="operationSuccess" role="status">
						{{ operationSuccess }}
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
								:value="driver.id">
								{{ driver.name }}{{ driver.available ? '' : ` — ${t('libresign', 'Unavailable')}` }}
							</option>
						</select>
					</template>
				</template>
				<a href="https://docs.libresign.coop/" target="_blank" rel="noopener noreferrer">
					{{ t('libresign', 'Administration documentation') }}
				</a>
			</div>
		</NcDialog>
	</section>
</template>

<script setup lang="ts">
import { mdiDelete, mdiPencil, mdiPower, mdiPowerOff } from '@mdi/js'
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { fetchExternalSigningDrivers } from '../../../services/externalSigningAdmin'
import { ExternalSigningAdminError } from '../../../services/externalSigningAdminOcs'
import type { ExternalSigningAdminAdapter, ExternalSigningDriver, ExternalSigningInstance } from '../../../services/externalSigningAdmin'
import ExternalSigningIntegrationForm from './ExternalSigningIntegrationForm.vue'

/** A production adapter is not supplied until the administrative CRUD API is available. */
const props = defineProps<{ adapter?: ExternalSigningAdminAdapter, catalogLoader?: () => Promise<ExternalSigningDriver[]>, layout?: 'cards' | 'compact', embedded?: boolean }>()
const drivers = ref<ExternalSigningDriver[]>([])
const visible = ref(false)
const dialogOpen = ref(false)
const instances = ref<ExternalSigningInstance[]>([])
const loading = ref(false)
const loadError = ref(false)
const loadErrorMessage = ref('')
const busy = ref(false)
const operationError = ref('')
const operationSuccess = ref('')
const selectedDriverId = ref('')
const selectedInstance = ref<ExternalSigningInstance | undefined>()
const selectedDriver = computed(() => driverById(selectedDriverId.value))

function driverById(id: string) {
	return drivers.value.find(driver => driver.id === id)
}
function isDriverAvailable(instance: ExternalSigningInstance): boolean {
	return instance.driverAvailable ?? driverById(instance.driverId)?.available ?? false
}
function getDriverStatusLabel(instance: ExternalSigningInstance): string {
	if (isDriverAvailable(instance)) {
		// TRANSLATORS The installed signing driver can be used for signing.
		return t('libresign', 'Driver available')
	}
	// TRANSLATORS The installed signing driver is missing or not operational.
	return t('libresign', 'Driver unavailable')
}
function getInstanceStatusLabel(instance: ExternalSigningInstance): string {
	if (instance.enabled) {
		// TRANSLATORS Status of an administratively enabled signing integration.
		return t('libresign', 'Enabled')
	}
	// TRANSLATORS Status of an administratively disabled signing integration.
	return t('libresign', 'Disabled')
}
function administrationErrorMessage(error: unknown): string {
	const status = error instanceof ExternalSigningAdminError ? error.status : null
	switch (status) {
	case 400:
		// TRANSLATORS External signing configuration validation failed on the server.
		return t('libresign', 'The server rejected this integration configuration.')
	case 401:
	case 403:
		// TRANSLATORS External signing administration is restricted to system administrators.
		return t('libresign', 'You are not authorized to manage external signing integrations.')
	case 404:
		// TRANSLATORS The external signing integration or experimental feature is unavailable.
		return t('libresign', 'The integration is unavailable. Refresh to check its status.')
	case 409:
		// TRANSLATORS The integration was updated elsewhere or cannot change while remote signing sessions are unresolved.
		return t('libresign', 'This integration was changed or has unresolved signing sessions. Refresh before trying again.')
	default:
		// TRANSLATORS An external signing administration operation failed.
		return t('libresign', 'The operation could not be completed. Refresh and try again.')
	}
}
function getActionsLabel(instance: ExternalSigningInstance): string {
	// TRANSLATORS Label for the actions menu of a configured external signing integration.
	return t('libresign', 'Actions for {name}', { name: instance.name })
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
async function edit(instance: ExternalSigningInstance) {
	if (!props.adapter || busy.value) {
		return
	}
	busy.value = true
	operationError.value = ''
	operationSuccess.value = ''
	try {
		const detail = props.adapter.get ? await props.adapter.get(instance) : instance
		selectedInstance.value = detail
		selectedDriverId.value = detail.driverId
	} catch (error) {
		operationError.value = administrationErrorMessage(error)
	} finally {
		busy.value = false
	}
}
async function load() {
	if (!props.adapter) {
		return
	}
	loading.value = true
	loadError.value = false
	loadErrorMessage.value = ''
	try {
		instances.value = await props.adapter.list()
	} catch (error) {
		loadError.value = true
		loadErrorMessage.value = administrationErrorMessage(error)
	} finally {
		loading.value = false
	}
}
async function onSaved(_instance: ExternalSigningInstance) {
	resetForm()
	await load()
	// TRANSLATORS Status after an external signing integration was saved on the server.
	operationSuccess.value = t('libresign', 'Integration saved.')
}
async function mutate(action: () => Promise<unknown>) {
	if (busy.value) {
		return
	}
	busy.value = true
	operationError.value = ''
	operationSuccess.value = ''
	try {
		await action()
		await load()
		// TRANSLATORS Status after an external signing integration's administrative state changed.
		operationSuccess.value = t('libresign', 'Integration updated.')
	} catch (error) {
		operationError.value = administrationErrorMessage(error)
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
void (props.catalogLoader ?? fetchExternalSigningDrivers)().then(data => {
		drivers.value = data
		visible.value = data.length > 0
	}).catch(() => {
		visible.value = false
	})
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

	&--embedded {
		min-height: 0;
		padding: 0;
		border: 0;
		border-radius: 0;
		background: transparent;

		.external-signing-admin__footer {
			margin-top: 0;
		}
	}

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
		box-sizing: border-box;
		width: 100%;
		min-width: 0;
		padding: 16px;
		display: grid;
		grid-template-columns: minmax(0, 1fr);
		gap: 12px;
		max-width: 100%;

		select {
			box-sizing: border-box;
			width: 100%;
			min-width: 0;
			max-width: 100%;
		}
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
		align-items: center;
		flex-shrink: 0;
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

		&__actions {
			align-self: flex-end;
		}
	}
}
</style>
