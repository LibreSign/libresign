<!--
- @copyright Copyright (c) 2021 Lyseon Tech <contato@lt.coop.br>
-
- @author Lyseon Tech <contato@lt.coop.br>
- @author Vinicios Gomes <viniciusgomesvaian@gmail.com>
-
- @license GNU AGPL version 3 or any later version
-
- This program is free software: you can redistribute it and/or modify
- it under the terms of the GNU Affero General Public License as
- published by the Free Software Foundation, either version 3 of the
- License, or (at your option) any later version.
-
- This program is distributed in the hope that it will be useful,
- but WITHOUT ANY WARRANTY; without even the implied warranty of
- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
- GNU Affero General Public License for more details.
-
- You should have received a copy of the GNU Affero General Public License
- along with this program.  If not, see <http://www.gnu.org/licenses/>.
-
-->

<template>
	<NcSettingsSection v-if="isThisEngine && (configureOk || cfsslBinariesOk)"
		:title="title"
		:description="description"
		:doc-url="docUrl">
		<div v-if="configureOk" id="tableRootCertificateCfssl" class="form-libresign">
			<table class="grid">
				<tbody>
					<tr>
						<td>{{ t('libresign', 'Name (CN)') }}</td>
						<td>{{ certificate.rootCert.commonName }}</td>
					</tr>
					<tr v-for="(customName) in certificate.rootCert.names" :key="customName.id" class="customNames">
						<td>{{ getCustomNamesOptionsById(customName.id) }} ({{ customName.id }})</td>
						<td>{{ customName.value }}</td>
					</tr>
					<tr>
						<td>{{ t('libresign', 'CFSSL API URI') }}</td>
						<td>{{ certificate.cfsslUri }}</td>
					</tr>
					<tr>
						<td>{{ t('libresign', 'Config path') }}</td>
						<td>{{ certificate.configPath }}</td>
					</tr>
				</tbody>
			</table>
			<NcButton v-if="cfsslBinariesOk"
				@click="showModal">
				{{ t('libresign', 'Regenerate root certificate') }}
			</NcButton>
			<NcModal v-if="modal"
				@close="closeModal">
				<div class="modal__content">
					<h2>{{ t('libresign', 'Confirm') }}</h2>
					{{ t('libresign', 'Regenerate root certificate will invalidate all signatures keys. Do you confirm this action?') }}
					<div class="grid">
						<NcButton type="error"
							@click="clearAndShowForm">
							{{ t('libresign', 'Yes') }}
						</NcButton>
						<NcButton type="primary"
							@click="closeModal">
							{{ t('libresign', 'No') }}
						</NcButton>
					</div>
				</div>
			</NcModal>
		</div>
		<div v-else-if="cfsslBinariesOk" id="formRootCertificate" class="form-libresign">
			<div class="form-group">
				<label for="certificateEngine" class="form-heading--required">{{ t('libresign', 'Certificate engine') }}</label>
				<NcMultiselect id="certificateEngine"
					v-model="certificateEngine"
					:options="certificateEngines"
					track-by="id"
					label="label"
					:placeholder="t('libresign', 'Select the certificate engine to generate the root certificate')"
					@change="onEngineChange" />
			</div>
			<div class="form-group">
				<label for="commonName" class="form-heading--required">{{ t('libresign', 'Name (CN)') }}</label>
				<NcTextField id="commonName"
					ref="commonName"
					:value.sync="certificate.rootCert.commonName"
					:helper-text="t('libresign', 'Full name of the main company or main user of this instance')"
					:minlength="1"
					:success="certificate.rootCert.commonName !== ''"
					:error="certificate.rootCert.commonName === ''"
					:disabled="formDisabled" />
			</div>
			<CertificateCustonOptions :certifiacteToSave.sync="certificateToSave" />
			<div>
				<NcCheckboxRadioSwitch v-if="!customData || !formDisabled"
					type="switch"
					:checked.sync="customData">
					{{ t('libresign', 'Define custom values to use {engine}', {engine: 'CFSSL'}) }}
				</NcCheckboxRadioSwitch>
			</div>
			<div v-if="customData" class="form-group">
				<label for="cfsslUri">{{ t('libresign', 'CFSSL API URI') }}</label>
				<NcTextField id="cfsslUri"
					:value.sync="certificate.cfsslUri"
					:label-outside="true"
					:placeholder="t('libresign', 'Not mandatory, don\'t fill to use default value.')"
					:disabled="formDisabled" />
			</div>
			<div v-if="customData" class="form-group">
				<label for="configPath">{{ t('libresign', 'Config path') }}</label>
				<NcTextField id="configPath"
					:value.sync="certificate.configPath"
					:label-outside="true"
					:placeholder="t('libresign', 'Not mandatory, don\'t fill to use default value.')"
					:disabled="formDisabled" />
			</div>
			<NcButton :disabled="formDisabled"
				@click="generateCertificate">
				{{ submitLabel }}
			</NcButton>
		</div>
	</NcSettingsSection>
</template>

<script>
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { subscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import CertificateCustonOptions from './CertificateCustonOptions.vue'

export default {
	name: 'RootCertificateCfssl',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
		NcModal,
		NcButton,
		NcTextField,
		CertificateCustonOptions,
	},
	data() {
		return {
			cfsslBinariesOk: false,
			configureOk: false,
			isThisEngine: loadState('libresign', 'certificate_engine') === 'cfssl',
			modal: false,
			certificateToSave: [],
			certificate: {
				rootCert: {
					commonName: '',
				},
				cfsslUri: '',
				configPath: '',
			},
			error: false,
			customData: false,
			title: t('libresign', 'Root certificate data.'),
			description: t('libresign', 'To generate new signatures, you must first generate the root certificate.'),
			submitLabel: t('libresign', 'Generate root certificate.'),
			docUrl: 'https://github.com/LibreSign/libresign/issues/1120',
			formDisabled: false,
			loading: true,
			customNamesOptions: [],
			certificateEngine: loadState('libresign', 'certificate_engine'),
			certificateEngines: [
				{ id: 'cfssl', label: 'cfssl' },
				{ id: 'openssl', label: 'OpenSSL' },
			],
		}
	},
	async mounted() {
		if (this.isThisEngine) {
			this.loadRootCertificate()
		}
		this.loading = false
		subscribe('libresign:certificate-engine:changed', this.changeEngine)
		this.$root.$on('after-config-check', data => {
			this.cfsslBinariesOk = data.filter((o) => o.resource === 'cfssl' && o.status === 'error').length === 0
			this.configureOk = data.filter((o) => o.resource === 'cfssl-configure' && o.status === 'error').length === 0
		})
	},

	methods: {
		changeEngine(engine) {
			this.isThisEngine = engine === 'cfssl'
			if (this.isThisEngine) {
				this.loadRootCertificate()
			}
		},
		showModal() {
			this.modal = true
		},
		closeModal() {
			this.modal = false
		},
		clearAndShowForm() {
			this.certificateToSave = []
			this.certificate.cfsslUri = ''
			this.certificate.configPath = ''
			this.certificate.generated = false
			this.customData = false
			this.configureOk = false
			this.formDisabled = false
			this.modal = false
			this.submitLabel = t('libresign', 'Generate root certificate.')
		},
		async generateCertificate() {
			this.formDisabled = true
			this.submitLabel = t('libresign', 'Generating certificate.')
			try {
				const response = await axios.post(
					generateOcsUrl('/apps/libresign/api/v1/admin/certificate/cfssl'),
					this.getDataToSave()
				)

				if (!response.data || response.data.message) {
					throw new Error(response.data)
				}
				this.certificate = response.data.data
				this.afterCertificateGenerated()
				this.$root.$emit('config-check')
				return
			} catch (e) {
				console.error(e)
				if (e.response.data.message) {
					showError(t('libresign', 'Could not generate certificate.') + '\n' + e.response.data.message)
				} else {
					showError(t('libresign', 'Could not generate certificate.'))
				}
				this.submitLabel = t('libresign', 'Generate root certificate.')

			}
			this.formDisabled = false
		},
		getDataToSave() {
			const data = {
				...this.certificate,
				rootCert: {
					...this.certificate.rootCert,
					names: this.certificateToSave,
				},
			}
			return data
		},

		async loadRootCertificate() {
			this.formDisabled = true
			try {
				const response = await axios.get(
					generateOcsUrl('/apps/libresign/api/v1/admin/certificate'),
				)
				if (!response.data || response.data.message) {
					throw new Error(response.data)
				}
				this.certificate = response.data
				this.configureOk = this.certificate.generated
				this.customData = this.certificate?.cfsslUri?.length > 0 || this.certificate.configPath.length > 0
				if (!Object.hasOwn(this.certificate.rootCert, 'commonName')) {
					this.$set(this.certificate.rootCert, 'commonName', '')
				}
				if (response.data.rootCert.commonName
				&& response.data.country
				&& response.data.organization
				&& response.data.organizationUnit
				&& this.configureOk
				) {
					this.afterCertificateGenerated()
					return
				}
			} catch (e) {
				console.error(e)
			}
			this.formDisabled = false
		},

		afterCertificateGenerated() {
			this.submitLabel = t('libresign', 'Generated certificate!')
			this.description = ''
			this.certificate.generated = true
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

.modal__content {
	margin: 50px;
	text-align: center;

	.grid {
		display: flex;
		flex-direction: row;
		align-self: flex-end;
		button {
			margin: 10px;
		}
	}
}

@media screen and (max-width: 500px){
	#formRootCertificateCfssl{
		width: 100%;
	}
}

</style>
