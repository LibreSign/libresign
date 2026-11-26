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
		:aria-label="embedded ? t('libresign', 'Digital signature services') : undefined"
		data-cy="external-signing-admin">
		<div class="external-signing-admin__entry">
			<div v-if="!embedded" class="external-signing-admin__details">
				<h3 id="external-signing-admin-title">
					{{ t('libresign', 'Digital signature services') }}
				</h3>
				<p>{{ t('libresign', 'Choose which digital signature services people covered by this rule can use.') }}</p>
				<p
					v-if="!adapter"
					id="external-signing-admin-unavailable"
					class="external-signing-admin__status"
					role="status">
					{{ t('libresign', 'Signing service settings are not available right now.') }}
				</p>
			</div>
			<div class="external-signing-admin__footer">
				<NcButton
					variant="secondary"
					:disabled="!adapter"
					:aria-describedby="!adapter ? 'external-signing-admin-unavailable' : undefined"
					@click="dialogOpen = true">
					{{ t('libresign', 'Manage signing services') }}
				</NcButton>
			</div>
		</div>
		<NcDialog
			v-if="adapter && dialogOpen"
			:name="t('libresign', 'Digital signature services')"
			size="normal"
			@closing="closeDialog">
			<div class="external-signing-admin__dialog">
				<p v-if="loading" role="status">
					{{ t('libresign', 'Loading signing services…') }}
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
						{{ t('libresign', 'No signing services have been added for this rule.') }}
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
									<NcActionButton :close-after-click="true" :disabled="busy" @click="requestRemoval(instance)">
										<template #icon>
											<NcIconSvgWrapper :path="mdiDelete" :size="20" />
										</template>
										{{ t('libresign', 'Remove') }}
									</NcActionButton>
								</NcActions>
							</div>
						</li>
					</ul>
					<div v-if="pendingRemoval" class="external-signing-admin__confirm" role="group" aria-labelledby="external-signing-remove-title">
						<p id="external-signing-remove-title">
							<!-- TRANSLATORS Confirmation prompt naming the exact external signing integration about to be removed. -->
							<strong>{{ t('libresign', 'Remove {name}?', { name: pendingRemoval.name }) }}</strong>
						</p>
						<p>{{ t('libresign', 'You cannot undo this action. An unfinished signature may prevent removal.') }}</p>
						<div class="external-signing-admin__confirm-actions">
							<NcButton variant="secondary" :disabled="busy" @click="pendingRemoval = null">
								{{ t('libresign', 'Keep service') }}
							</NcButton>
							<NcButton variant="error" :disabled="busy" @click="confirmRemoval">
								{{ t('libresign', 'Remove service') }}
							</NcButton>
						</div>
					</div>
					<p v-if="operationError" role="alert">
						{{ operationError }}
					</p>
					<p v-if="operationSuccess" role="status">
						{{ operationSuccess }}
					</p>
					<NcButton
						v-if="!creating && !selectedDriver"
						variant="primary"
						:disabled="busy"
						@click="beginCreate">
						{{ t('libresign', 'Add signing service') }}
					</NcButton>
					<template v-if="selectedDriver">
						<ExternalSigningIntegrationForm
							:key="selectedInstance?.id ?? `new-${selectedDriver.id}`"
							:driver="selectedDriver"
							:existing="selectedInstance"
							:save="adapter.save"
							@saved="onSaved"
							@cancel="resetForm" />
					</template>
					<template v-else-if="creating">
						<label for="external-signing-driver">{{ t('libresign', 'Signing service type') }}</label>
						<select id="external-signing-driver" v-model="selectedDriverId">
							<option value="">
								{{ t('libresign', 'Choose a signing service') }}
							</option>
							<option
								v-for="driver in drivers"
								:key="driver.id"
								:value="driver.id">
								{{ driver.name }}{{ driver.available ? '' : ` — ${t('libresign', 'Unavailable')}` }}
							</option>
						</select>
						<NcButton variant="secondary" :disabled="busy" @click="resetForm">
							{{ t('libresign', 'Cancel') }}
						</NcButton>
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
const creating = ref(false)
const pendingRemoval = ref<ExternalSigningInstance | null>(null)
let loadGeneration = 0
let editGeneration = 0
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
		return t('libresign', 'Connector installed')
	}
	// TRANSLATORS The installed signing driver is missing or not operational.
	return t('libresign', 'Connector unavailable')
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
		return t('libresign', 'The service settings could not be saved. Check the information and try again.')
	case 401:
	case 403:
		// TRANSLATORS External signing administration is restricted to system administrators.
		return t('libresign', 'You do not have permission to manage signing services.')
	case 404:
		// TRANSLATORS The external signing integration or experimental feature is unavailable.
		return t('libresign', 'This signing service is unavailable. Refresh to check again.')
	case 409:
		// TRANSLATORS The integration was updated elsewhere or cannot change while remote signing sessions are unresolved.
		return t('libresign', 'This service was changed elsewhere or has unfinished signatures. Refresh before trying again.')
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
	creating.value = false
}
function beginCreate() {
	resetForm()
	operationError.value = ''
	operationSuccess.value = ''
	creating.value = true
	// A provider type should only be a choice when there are alternatives.
	if (drivers.value.length === 1) {
		selectedDriverId.value = drivers.value[0].id
	}
}
function closeDialog() {
	if (!busy.value) {
		dialogOpen.value = false
		loadGeneration++
		editGeneration++
		pendingRemoval.value = null
		resetForm()
	}
}
async function edit(instance: ExternalSigningInstance) {
	if (!props.adapter || busy.value) {
		return
	}
	const current = ++editGeneration
	busy.value = true
	operationError.value = ''
	operationSuccess.value = ''
	try {
		const detail = props.adapter.get ? await props.adapter.get(instance) : instance
		if (current === editGeneration && dialogOpen.value) {
			selectedInstance.value = detail
			selectedDriverId.value = detail.driverId
			creating.value = false
		}
	} catch (error) {
		if (current === editGeneration && dialogOpen.value) {
			operationError.value = administrationErrorMessage(error)
		}
	} finally {
		if (current === editGeneration) {
			busy.value = false
		}
	}
}
async function load(): Promise<boolean> {
	if (!props.adapter) {
		return false
	}
	const current = ++loadGeneration
	loading.value = true
	loadError.value = false
	loadErrorMessage.value = ''
	try {
		const result = await props.adapter.list()
		if (current !== loadGeneration || !dialogOpen.value) {
			return false
		}
		instances.value = result
		return true
	} catch (error) {
		if (current === loadGeneration && dialogOpen.value) {
			loadError.value = true
			loadErrorMessage.value = administrationErrorMessage(error)
		}
		return false
	} finally {
		if (current === loadGeneration) {
			loading.value = false
		}
	}
}
function handleReloadResult(reloaded: boolean, savedMessage: string) {
	if (reloaded) {
		operationSuccess.value = savedMessage
	} else if (dialogOpen.value) {
		// TRANSLATORS The mutation succeeded but the refreshed list could not be retrieved.
		loadErrorMessage.value = t('libresign', 'The change was saved, but the service list could not be refreshed. Try again.')
	}
}
async function onSaved(_instance: ExternalSigningInstance) {
	resetForm()
	const reloaded = await load()
	// TRANSLATORS Status after an external signing integration was saved on the server.
	handleReloadResult(reloaded, t('libresign', 'Signing service saved.'))
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
		const reloaded = await load()
		// TRANSLATORS Status after an external signing integration's administrative state changed.
		handleReloadResult(reloaded, t('libresign', 'Signing service updated.'))
	} catch (error) {
		operationError.value = administrationErrorMessage(error)
	} finally {
		busy.value = false
	}
}
function toggle(instance: ExternalSigningInstance) {
	void mutate(() => props.adapter!.setEnabled(instance, !instance.enabled))
}
function requestRemoval(instance: ExternalSigningInstance) {
	if (busy.value) {
		return
	}
	pendingRemoval.value = instance
	operationError.value = ''
	operationSuccess.value = ''
}
function confirmRemoval() {
	const target = pendingRemoval.value
	if (!target || busy.value) {
		return
	}
	pendingRemoval.value = null
	void mutate(() => props.adapter!.remove(target))
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
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
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
		border-radius: var(--border-radius-large);
		background: var(--color-main-background);

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

			> div:first-child {
				min-width: 0;
				overflow-wrap: anywhere;
			}

			p {
				color: var(--color-text-maxcontrast);
			}
		}
	}

	&__actions {
		display: flex;
		align-items: center;
		flex-shrink: 0;
	}

	&__confirm {
		padding: 12px;
		border: 1px solid var(--color-warning);
		border-radius: var(--border-radius-large);

		p {
			margin: 0 0 8px;
		}
	}

	&__confirm-actions {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
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
