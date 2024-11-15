<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection v-if="isThisEngine && loaded"
		:name="name"
		:description="description">
		<div v-if="configureOk && !isCertificateGenerated" id="tableRootCertificateOpenSsl" class="form-libresign">
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
						<td>{{ t('libresign', 'Config path') }}</td>
						<td>{{ certificate.configPath }}</td>
					</tr>
				</tbody>
			</table>
			<NcButton type="primary" @click="showModal">
				{{ t('libresign', 'Regenerate root certificate') }}
			</NcButton>
			<NcDialog v-if="modal"
				:name="t('libresign', 'Confirm')"
				@closing="closeModal">
				{{ t('libresign', 'Regenerate root certificate will invalidate all signatures keys. Do you confirm this action?') }}
				<template #actions>
					<NcButton type="error"
						@click="clearAndShowForm">
						{{ t('libresign', 'Yes') }}
					</NcButton>
					<NcButton type="primary"
						@click="closeModal">
						{{ t('libresign', 'No') }}
					</NcButton>
				</template>
			</NcDialog>
		</div>
		<div v-else id="formRootCertificateOpenSsl" class="form-libresign">
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
				<NcCheckboxRadioSwitch v-if="!customData || !formDisabled"
					type="switch"
					:checked.sync="customData">
					{{ t('libresign', 'Define custom values to use {engine}', {engine: 'OpenSSL'}) }}
				</NcCheckboxRadioSwitch>
			</div>
			<div v-if="customData" class="form-group">
				<label for="configPath">{{ t('libresign', 'Config path') }}</label>
				<NcTextField id="configPath"
					v-model="certificate.configPath"
					:label-outside="true"
					:placeholder="t('libresign', 'Not mandatory, don\'t fill to use default value.')"
					:disabled="formDisabled" />
			</div>
			<NcButton :disabled="formDisabled"
				type="primary"
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

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import CertificateCustonOptions from './CertificateCustonOptions.vue'

import { selectCustonOption } from '../../helpers/certification.js'
import { useConfigureCheckStore } from '../../store/configureCheck.js'

export default {
	name: 'RootCertificateOpenSsl',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcButton,
		NcTextField,
		CertificateCustonOptions,
	},
	setup() {
		const configureCheckStore = useConfigureCheckStore()
		return { configureCheckStore }
	},
	data() {
		return {
			isThisEngine: loadState('libresign', 'certificate_engine') === 'openssl',
			modal: false,
			certificate: {
				rootCert: {
					commonName: '',
					names: [],
				},
				configPath: '',
			},
			error: false,
			customData: false,
			name: t('libresign', 'Root certificate data'),
			description: t('libresign', 'To generate new signatures, you must first generate the root certificate.'),
			submitLabel: t('libresign', 'Generate root certificate'),
			formDisabled: false,
		}
	},
	computed: {
		configureOk() {
			return this.configureCheckStore.isConfigureOk('openssl')
		},
		isCertificateGenerated() {
			return this.certificate.rootCert.names.length > 0
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
		updateNames(names) {
			this.certificate.rootCert.names = names
		},
		getLabelFromId(id) {
			const item = selectCustonOption(id).unwrap()
			return item.label
		},
		changeEngine(engine) {
			this.isThisEngine = engine === 'openssl'
			this.loadRootCertificate()
		},
		showModal() {
			this.modal = true
		},
		closeModal() {
			this.modal = false
		},
		clearAndShowForm() {
			this.certificate = {
				rootCert: {
					commonName: '',
					names: [],
				},
				configPath: '',
			}
			this.customData = false
			this.formDisabled = false
			this.modal = false
			this.submitLabel = t('libresign', 'Generate root certificate')
		},
		async generateCertificate() {
			this.formDisabled = true
			this.submitLabel = t('libresign', 'Generating certificate.')
			try {
				const response = await axios.post(
					generateOcsUrl('/apps/libresign/api/v1/admin/certificate/openssl'),
					this.getDataToSave(),
				)

				if (!response.data.ocs.data || response.data.ocs.data.message) {
					throw new Error(response.data.ocs.data)
				}
				this.certificate = response.data.ocs.data.data
				this.afterCertificateGenerated()
				this.configureCheckStore.checkSetup()
				return
			} catch (e) {
				console.error(e)
				if (e.response.data.ocs.data.message) {
					showError(t('libresign', 'Could not generate certificate.') + '\n' + e.response.data.ocs.data.message)
				} else {
					showError(t('libresign', 'Could not generate certificate.'))
				}
				this.submitLabel = t('libresign', 'Generate root certificate')

			}
			this.formDisabled = false
		},
		getDataToSave() {
			if (!this.customData) {
				this.certificate.configPath = ''
			}
			return this.certificate
		},
		async loadRootCertificate() {
			if (!this.isThisEngine) {
				return
			}
			this.formDisabled = true
			try {
				const response = await axios.get(
					generateOcsUrl('/apps/libresign/api/v1/admin/certificate'),
				)
				if (!response.data.ocs.data || response.data.ocs.data.message) {
					throw new Error(response.data.ocs.data)
				}
				this.certificate = response.data.ocs.data
				this.customData = loadState('libresign', 'config_path').length > 0 && this.certificate.configPath.length > 0
				if (this.certificate.generated) {
					this.afterCertificateGenerated()
				}
			} catch (e) {
				console.error(e)
			}
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
#formRootCertificateOpenSsl{
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
	#formRootCertificateOpenSsl{
		width: 100%;
	}
}

</style>
