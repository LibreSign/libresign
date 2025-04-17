<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection v-if="isThisEngine && loaded && configureCheckStore.cfsslBinariesOk()"
		:name="name"
		:description="description">
		<div v-if="configureOk && isCertificateGenerated" id="tableRootCertificateCfssl" class="form-libresign">
			<table class="grid">
				<tbody>
					<tr>
						<td>{{ t('libresign', 'Name (CN)') }}</td>
						<td>{{ certificate.rootCert.commonName }}</td>
					</tr>
					<tr v-for="(customName) in certificate.rootCert.names" :key="customName.id" class="customNames">
						<td>{{ getLabelFromId(customName.id) }} ({{ customName.id }})</td>
						<td>{{ customName.value }}</td>
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
				<label for="commonName" class="form-heading--required">{{ t('libresign', 'Name (CN)') }}</label>
				<NcTextField id="commonName"
					ref="commonName"
					v-model="certificate.rootCert.commonName"
					:helper-text="t('libresign', 'Full name of the main company or main user of this instance')"
					:minlength="1"
					:success="certificate.rootCert.commonName !== ''"
					:error="certificate.rootCert.commonName === ''"
					:disabled="formDisabled" />
			</div>
			<CertificateCustonOptions :names.sync="certificate.rootCert.names" />
			<div>
				<NcCheckboxRadioSwitch :disabled="formDisabled"
					type="switch"
					:checked.sync="toggleCertificatePolicy">
					{{ t('libresign', 'Include certificate policy') }}
				</NcCheckboxRadioSwitch>
			</div>
			<CertificatePolicy v-if="toggleCertificatePolicy"
				:disabled="formDisabled"
				@certificate-policy-valid="handleCertificatePolicyValid" />
			<div>
				<NcCheckboxRadioSwitch :disabled="formDisabled"
					type="switch"
					:checked.sync="customData">
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

<script>
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import CertificateCustonOptions from './CertificateCustonOptions.vue'
import CertificatePolicy from './CertificatePolicy.vue'

import { selectCustonOption } from '../../helpers/certification.js'
import logger from '../../logger.js'
import { useConfigureCheckStore } from '../../store/configureCheck.js'

export default {
	name: 'RootCertificateCfssl',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcButton,
		NcTextField,
		CertificateCustonOptions,
		CertificatePolicy,
	},
	setup() {
		const configureCheckStore = useConfigureCheckStore()
		return { configureCheckStore }
	},
	data() {
		const OID = loadState('libresign', 'certificate_policies_oid')
		const CPS = loadState('libresign', 'certificate_policies_cps')
		return {
			isThisEngine: loadState('libresign', 'certificate_engine') === 'cfssl',
			modal: false,
			certificate: {
				rootCert: {
					commonName: '',
					names: [],
				},
				cfsslUri: '',
				configPath: '',
				generated: false,
			},
			error: false,
			customData: false,
			name: t('libresign', 'Root certificate data'),
			description: t('libresign', 'To generate new signatures, you must first generate the root certificate.'),
			submitLabel: t('libresign', 'Generate root certificate'),
			formDisabled: false,
			OID,
			CPS,
			toggleCertificatePolicy: !!(OID || CPS),
			certificatePolicyValid: !!OID && !!CPS,
		}
	},
	computed: {
		includeCertificatePolicy() {
			return this.toggleCertificatePolicy || this.CPS || this.OID
		},
		canSave() {
			if (this.formDisabled) {
				return false
			}
			if (!this.toggleCertificatePolicy) {
				return true
			}
			return this.certificatePolicyValid
		},
		configureOk() {
			return this.configureCheckStore.isConfigureOk('cfssl')
		},
		isCertificateGenerated() {
			return this.certificate.generated
		},
		loaded() {
			return this.configureCheckStore.items.length > 0
		},
	},
	async mounted() {
		this.loadRootCertificate()
		subscribe('libresign:certificate-engine:changed', this.changeEngine)
		subscribe('libresign:update:certificateToSave', this.updateNames)
	},
	beforeUnmount() {
		unsubscribe('libresign:certificate-engine:changed')
		unsubscribe('libresign:update:certificateToSave')
	},

	methods: {
		handleCertificatePolicyValid(isValid) {
			this.certificatePolicyValid = isValid
		},
		updateNames(names) {
			this.certificate.rootCert.names = names
		},
		getLabelFromId(id) {
			const item = selectCustonOption(id).unwrap()
			return item.label
		},
		changeEngine(engine) {
			this.isThisEngine = engine === 'cfssl'
			this.loadRootCertificate()
		},
		showModal() {
			this.modal = true
		},
		closeModal() {
			this.modal = false
		},
		clearAndShowForm() {
			this.$set(this, 'certificate', {
				rootCert: {
					commonName: '',
					names: [],
				},
				cfsslUri: '',
				configPath: '',
			})
			this.customData = false
			this.formDisabled = false
			this.modal = false
			this.submitLabel = t('libresign', 'Generate root certificate')
		},
		async generateCertificate() {
			this.formDisabled = true
			this.submitLabel = t('libresign', 'Generating certificate.')
			await axios.post(
				generateOcsUrl('/apps/libresign/api/v1/admin/certificate/cfssl'),
				this.getDataToSave(),
			)
				.then(({ data }) => {
					if (!data.ocs.data || data.ocs.data.message) {
						throw new Error(data.ocs.data)
					}
					this.$set(this, 'certificate', data.ocs.data.data)
					this.afterCertificateGenerated()
					this.configureCheckStore.checkSetup()
				})
				.catch(({ response }) => {
					if (response?.data?.ocs?.data?.message?.length > 0) {
						showError(t('libresign', 'Could not generate certificate.') + '\n' + response.data.ocs.data.message)
					} else if (response.length) {
						showError(t('libresign', 'Could not generate certificate.') + '\n' + response)
					} else {
						showError(t('libresign', 'Could not generate certificate.'))
					}
					this.submitLabel = t('libresign', 'Generate root certificate')
				})
			this.formDisabled = false
		},
		getDataToSave() {
			if (!this.customData) {
				this.certificate.configPath = ''
				this.certificate.cfsslUri = ''
			}
			return this.certificate
		},
		async loadRootCertificate() {
			if (!this.isThisEngine) {
				return
			}
			this.formDisabled = true
			await axios.get(generateOcsUrl('/apps/libresign/api/v1/admin/certificate'))
				.then(({ data }) => {
					if (!data.ocs.data || data.ocs.data.message) {
						throw new Error(data.ocs.data)
					}
					this.$set(this, 'certificate', data.ocs.data)
					this.customData = loadState('libresign', 'config_path').length > 0
						&& (this.certificate?.cfsslUri?.length > 0 || this.certificate.configPath.length > 0)
					if (this.certificate.generated) {
						this.afterCertificateGenerated()

					}
				})
				.catch((error) => logger.debug('Error when generate certificate', { error }))
			this.formDisabled = false
		},

		afterCertificateGenerated() {
			this.submitLabel = t('libresign', 'Generated certificate!')
			this.description = ''
		},
	},
}
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

.form-heading--required:after {
	content:" *";
}

@media screen and (max-width: 500px){
	#formRootCertificateCfssl{
		width: 100%;
	}
}

</style>
