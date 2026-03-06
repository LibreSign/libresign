<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection v-if="isThisEngine && loaded && configureCheckStore.cfsslBinariesOk()"
		:name="t('libresign', 'Root certificate data')"
		:description="description">
		<div v-if="configureOk && isCertificateGenerated" id="tableRootCertificateCfssl" class="form-libresign">
			<table class="grid">
				<tbody>
					<tr>
						<td>{{ t('libresign', 'Common Name (CN)') }}</td>
						<td>{{ certificate.rootCert.commonName }}</td>
					</tr>
					<tr v-for="(customName) in certificate.rootCert.names" :key="customName.id" class="customNames">
						<td>{{ getLabelFromId(customName.id) }} ({{ customName.id }})</td>
						<td>
							<ul v-if="Array.isArray(customName.value)" class="certificate-list">
								<li v-for="(item, index) in customName.value" :key="index">
									{{ item }}
								</li>
							</ul>
							<span v-else>{{ customName.value }}</span>
						</td>
					</tr>
					<tr>
						<td>{{ t('libresign', 'CFSSL API URI') }}</td>
						<td>{{ certificate.cfsslUri }}</td>
					</tr>
					<tr v-if="OID" class="customNames">
						<td>OID</td>
						<td>{{ OID }}</td>
					</tr>
					<tr v-if="CPS" class="customNames">
						<td>CPS</td>
						<td>{{ CPS }}</td>
					</tr>
					<tr>
						<td>{{ t('libresign', 'Config path') }}</td>
						<td>{{ certificate.configPath }}</td>
					</tr>
				</tbody>
			</table>
			<NcButton variant="primary" @click="showModal">
					{{ t('libresign', 'Regenerate root certificate') }}
			</NcButton>
			<NcDialog v-if="modal"
				:name="t('libresign', 'Confirm')"
				@closing="closeModal">
				{{ t('libresign', 'Regenerate root certificate will invalidate all signatures keys. Do you confirm this action?') }}
				<template #actions>
					<NcButton variant="error"
						@click="clearAndShowForm">
						{{ t('libresign', 'Yes') }}
					</NcButton>
					<NcButton variant="primary"
						@click="closeModal">
						{{ t('libresign', 'No') }}
					</NcButton>
				</template>
			</NcDialog>
		</div>
		<div v-else id="formRootCertificate" class="form-libresign">
			<div class="form-group">
				<label for="commonName" class="form-heading--required">{{ t('libresign', 'Common Name (CN)') }}</label>
				<NcTextField id="commonName"
					ref="commonName"
					v-model="certificate.rootCert.commonName"
					:helper-text="t('libresign', 'Full name of the main company or main user of this instance')"
					:minlength="1"
					:success="certificate.rootCert.commonName !== ''"
					:error="certificate.rootCert.commonName === ''"
					:disabled="formDisabled" />
			</div>
			<CertificateCustonOptions v-model:names="certificate.rootCert.names" />
			<div>
				<NcCheckboxRadioSwitch :disabled="formDisabled"
					type="switch"
					v-model="toggleCertificatePolicy">
					{{ t('libresign', 'Include certificate policy') }}
				</NcCheckboxRadioSwitch>
			</div>
			<CertificatePolicy v-if="toggleCertificatePolicy"
				:disabled="formDisabled"
				@certificate-policy-valid="handleCertificatePolicyValid" />
			<div>
				<NcCheckboxRadioSwitch :disabled="formDisabled"
					type="switch"
					v-model="customData">
					{{ t('libresign', 'Define custom values to use {engine}', {engine: 'CFSSL'}) }}
				</NcCheckboxRadioSwitch>
			</div>
			<div v-if="customData" class="form-group">
				<label for="cfsslUri">{{ t('libresign', 'CFSSL API URI') }}</label>
				<NcTextField id="cfsslUri"
					v-model="certificate.cfsslUri"
					:label-outside="true"
					:placeholder="t('libresign', 'Not mandatory, leave blank to use the default value.')"
					:disabled="formDisabled" />
			</div>
			<div v-if="customData" class="form-group">
				<label for="configPath">{{ t('libresign', 'Config path') }}</label>
				<NcTextField id="configPath"
					v-model="certificate.configPath"
					:label-outside="true"
					:placeholder="t('libresign', 'Not mandatory, leave blank to use the default value.')"
					:disabled="formDisabled" />
			</div>
			<NcButton :disabled="!canSave"
				@click="generateCertificate">
				{{ submitLabel }}
			</NcButton>
		</div>
	</NcSettingsSection>
</template>

<script setup lang="ts">
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import CertificateCustonOptions from './CertificateCustonOptions.vue'
import CertificatePolicy from './CertificatePolicy.vue'

import { selectCustonOption } from '../../helpers/certification'
import logger from '../../logger.js'
import { useConfigureCheckStore } from '../../store/configureCheck.js'

type CertificateName = {
	id: string
	value: string | string[]
}

type RootCertificatePayload = {
	generated?: boolean
	rootCert: {
		commonName: string
		names: CertificateName[]
	}
	cfsslUri: string
	configPath: string
}

defineOptions({
	name: 'RootCertificateCfssl',
})

const configureCheckStore = useConfigureCheckStore()
const OID = loadState('libresign', 'certificate_policies_oid', '')
const CPS = loadState('libresign', 'certificate_policies_cps', '')

const isThisEngine = ref(loadState('libresign', 'certificate_engine', '') === 'cfssl')
const modal = ref(false)
const certificate = ref<RootCertificatePayload>({
	rootCert: {
		commonName: '',
		names: [],
	},
	cfsslUri: '',
	configPath: '',
	generated: false,
})
const error = ref(false)
const customData = ref(false)
const formDisabled = ref(false)
const toggleCertificatePolicy = ref(!!(OID || CPS))
const certificatePolicyValid = ref(!!CPS || (!!CPS && !!OID))
const description = ref('')
const submitLabel = ref('')

const includeCertificatePolicy = computed(() => toggleCertificatePolicy.value || !!CPS || !!OID)
const canSave = computed(() => {
	if (formDisabled.value) {
		return false
	}
	if (!toggleCertificatePolicy.value) {
		return true
	}
	return certificatePolicyValid.value
})
const configureOk = computed(() => configureCheckStore.isConfigureOk('cfssl'))
const isCertificateGenerated = computed(() => !!certificate.value.generated)
const loaded = computed(() => configureCheckStore.items.length > 0)

function handleCertificatePolicyValid(isValid: boolean) {
	certificatePolicyValid.value = isValid
}

function updateNames(names: CertificateName[]) {
	certificate.value.rootCert.names = names
}

function getLabelFromId(id: string) {
	return selectCustonOption(id).unwrap().label
}

async function changeEngine(engine: string) {
	isThisEngine.value = engine === 'cfssl'
	await loadRootCertificate()
}

function showModal() {
	modal.value = true
}

function closeModal() {
	modal.value = false
}

function clearAndShowForm() {
	certificate.value = {
		rootCert: {
			commonName: '',
			names: [],
		},
		cfsslUri: '',
		configPath: '',
		generated: false,
	}
	customData.value = false
	formDisabled.value = false
	modal.value = false
	submitLabel.value = t('libresign', 'Generate root certificate')
}

async function generateCertificate() {
	formDisabled.value = true
	submitLabel.value = t('libresign', 'Generating certificate.')

	try {
		const { data } = await axios.post(
			generateOcsUrl('/apps/libresign/api/v1/admin/certificate/cfssl'),
			getDataToSave(),
		)

		if (!data.ocs.data || data.ocs.data.message) {
			throw new Error(data.ocs.data)
		}

		certificate.value = data.ocs.data.data
		afterCertificateGenerated()
		configureCheckStore.checkSetup()
	} catch (caughtError: any) {
		const response = caughtError?.response
		if (response?.data?.ocs?.data?.message?.length > 0) {
			showError(t('libresign', 'Could not generate certificate.') + '\n' + response.data.ocs.data.message)
		} else if (response?.length) {
			showError(t('libresign', 'Could not generate certificate.') + '\n' + response)
		} else {
			showError(t('libresign', 'Could not generate certificate.'))
		}
		submitLabel.value = t('libresign', 'Generate root certificate')
	} finally {
		formDisabled.value = false
	}
}

function getDataToSave() {
	if (!customData.value) {
		certificate.value.configPath = ''
		certificate.value.cfsslUri = ''
	}
	return certificate.value
}

async function loadRootCertificate() {
	if (!isThisEngine.value) {
		return
	}

	formDisabled.value = true
	try {
		const { data } = await axios.get(generateOcsUrl('/apps/libresign/api/v1/admin/certificate'))
		if (!data.ocs.data || data.ocs.data.message) {
			throw new Error(data.ocs.data)
		}

		certificate.value = data.ocs.data
		customData.value = loadState('libresign', 'config_path', '').length > 0
			&& (!!certificate.value.cfsslUri?.length || certificate.value.configPath.length > 0)
		if (certificate.value.generated) {
			afterCertificateGenerated()
		}
	} catch (caughtError) {
		logger.debug('Error when generate certificate', { error: caughtError })
	} finally {
		formDisabled.value = false
	}
}

function afterCertificateGenerated() {
	submitLabel.value = t('libresign', 'Generated certificate!')
	description.value = ''
}

onMounted(() => {
	description.value = t('libresign', 'To generate new signatures, you must first generate the root certificate.')
	submitLabel.value = t('libresign', 'Generate root certificate')
	void loadRootCertificate()
	subscribe('libresign:certificate-engine:changed', changeEngine)
	subscribe('libresign:update:certificateToSave', updateNames)
})

onBeforeUnmount(() => {
	unsubscribe('libresign:certificate-engine:changed')
	unsubscribe('libresign:update:certificateToSave')
})

defineExpose({
	t,
	configureCheckStore,
	OID,
	CPS,
	isThisEngine,
	modal,
	certificate,
	error,
	customData,
	formDisabled,
	toggleCertificatePolicy,
	certificatePolicyValid,
	description,
	submitLabel,
	includeCertificatePolicy,
	canSave,
	configureOk,
	isCertificateGenerated,
	loaded,
	handleCertificatePolicyValid,
	updateNames,
	getLabelFromId,
	changeEngine,
	showModal,
	closeModal,
	clearAndShowForm,
	generateCertificate,
	getDataToSave,
	loadRootCertificate,
	afterCertificateGenerated,
})
</script>
<style lang="scss" scoped>
#formRootCertificateCfssl{
	text-align: left;
	margin: 20px;
}

.form-group > input[type='text'], .form-group .multiselect {
	width: 100%;
}

.customNames {
	.item {
		display: grid;
		grid-template-columns: auto 54px;
		input[type='text'] {
			width: 100%;
		}
		.button-vue {
			margin-left: 10px;
		}
	}
}

.certificate-list {
	margin: 0;
	padding-left: 16px;
	li {
		margin: 2px 0;
	}
}

.form-heading--required:after {
	content:" *";
}

@media screen and (max-width: 500px){
	#formRootCertificateCfssl{
		width: 100%;
	}
}

</style>
