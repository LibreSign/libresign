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
	<NcSettingsSection :title="title"
		:description="description"
		:doc-url="docUrl"
		v-if="cfsslConfigureOk || cfsslBinariesOk">
		<div id="tableRootCertificate" class="form-libresign" v-if="cfsslConfigureOk">
			<table class="grid">
				<tbody>
					<tr>
						<td>{{ t('libresign', 'Name (CN)') }}</td>
						<td>{{ certificate.rootCert.commonName }}</td>
					</tr>
					<tr class="customNames" v-for="(customName) in certificate.rootCert.names" :key="customName.id">
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
			<NcButton
				@click="showModal" v-if="cfsslBinariesOk">
				{{ t('libresign', 'Regenerate root certificate') }}
			</NcButton>
			<NcModal
				v-if="modal"
				@close="closeModal"
				title="Title inside modal">
				<div class="modal__content">
					<h2>{{ t('libresign', 'Confirm') }}</h2>
					{{ t('libresign', 'Regenerate root certificate will invalidate all signatures keys. Do you confirm this action?')}}
					<div class="grid">
						<NcButton
							type="error"
							@click="clearAndShowForm">
							{{ t('libresign', 'Yes') }}
						</NcButton>
						<NcButton
							type="primary"
							@click="closeModal">
							{{ t('libresign', 'No') }}
						</NcButton>
					</div>
				</div>
			</NcModal>
		</div>
		<div v-else-if="cfsslBinariesOk" id="formRootCertificate" class="form-libresign">
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
			<div class="form-group">
				<label for="optionalAttribute">{{ t('libresign', 'Optional attributes') }}</label>
				<NcMultiselect
					id=optionalAttribute
					:options=customNamesOptions
					track-by="id"
					label="label"
					:placeholder="t('libresign', 'Select a custom name')"
					@change="onOptionalAttributeSelect"
					/>
			</div>
			<div class="customNames" v-for="(customName, key) in certificate.rootCert.names" :key="customName.id">
				<label :for="customName.id" class="form-heading--required">
					{{ getCustomNamesOptionsById(customName.id) }} ({{ customName.id }})
				</label>
				<div class="item">
					<NcTextField :id="customName.id"
						:value.sync="customName.value"
						:success="typeof customName.error === 'boolean' && !customName.error"
						:error="customName.error"
						:maxlength="customName.maxlength"
						:helper-text="customName.helperText"
						@update:value="validate(customName.id)"
						v-if="customName"
						:disabled="formDisabled" />
					<NcButton
						:aria-label="t('settings', 'Remove custom name entry from root certificate')"
						@click="removeOptionalAttribute(key)">
						<template #icon>
							<Delete :size="20" />
						</template>
					</NcButton>
				</div>
			</div>
			<div>
				<NcCheckboxRadioSwitch
					type="switch"
					v-if="!customCfsslData || !formDisabled"
					:checked.sync="customCfsslData">
					{{ t('libresign', 'Define custom values to use CFSSL') }}
				</NcCheckboxRadioSwitch>
			</div>
			<div class="form-group" v-if="customCfsslData">
				<label for="cfsslUri">{{ t('libresign', 'CFSSL API URI') }}</label>
				<NcTextField id="cfsslUri"
					:value.sync="certificate.cfsslUri"
					:label-outside="true"
					:placeholder="t('libresign', 'Not mandatory, don\'t fill to use default value.')"
					:disabled="formDisabled" />
			</div>
			<div class="form-group" v-if="customCfsslData">
				<label for="configPath">{{ t('libresign', 'Config path') }}</label>
				<NcTextField id="configPath"
					:value.sync="certificate.configPath"
					:label-outside="true"
					:placeholder="t('libresign', 'Not mandatory, don\'t fill to use default value.')"
					:disabled="formDisabled" />
			</div>
			<input type="button"
				class="primary"
				:value="submitLabel"
				:disabled="formDisabled || !savePossible"
				@click="generateCertificate">
		</div>
	</NcSettingsSection>
</template>

<script>
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch'
import NcMultiselect from '@nextcloud/vue/dist/Components/NcMultiselect'
import NcModal from '@nextcloud/vue/dist/Components/NcModal'
import NcButton from '@nextcloud/vue/dist/Components/NcButton'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField'
import Delete from 'vue-material-design-icons/Delete.vue'
import '@nextcloud/dialogs/styles/toast.scss'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'AdminFormLibresign',
	components: {
		NcSettingsSection,
		NcMultiselect,
		NcCheckboxRadioSwitch,
		NcModal,
		NcButton,
		NcTextField,
		Delete,
	},
	data() {
		return {
			cfsslBinariesOk: false,
			cfsslConfigureOk: false,
			modal: false,
			certificate: {
				rootCert: {
					commonName: '',
					names: {},
				},
				cfsslUri: '',
				configPath: '',
			},
			rootCertDataset: {
				C: {
					id: 'C',
					label: 'Country',
					min: 2,
					max: 2,
					minHelper: t('libresign', 'Two-letter ISO 3166 country code'),
					defaultHelper: t('libresign', 'Two-letter ISO 3166 country code'),
				},
				ST: {
					id: 'ST',
					label: 'State',
					min: 1,
					defaultHelper: t('libresign', 'Full name of states or provinces'),
				},
				L: {
					id: 'L',
					label: 'Locality',
					min: 1,
					defaultHelper: t('libresign', 'Name of a locality or place, such as a city, county, or other geographic region'),
				},
				O: {
					id: 'O',
					label: 'Organization',
					min: 1,
					defaultHelper: t('libresign', 'Name of an organization'),
				},
				OU: {
					id: 'OU',
					label: 'OrganizationalUnit',
					min: 1,
					defaultHelper: t('libresign', 'Name of an organizational unit'),
				},
			},
			error: false,
			customCfsslData: false,
			title: t('libresign', 'Root certificate data.'),
			description: t('libresign', 'To generate new signatures, you must first generate the root certificate.'),
			submitLabel: t('libresign', 'Generate root certificate.'),
			docUrl: 'https://github.com/LibreSign/libresign/issues/1120',
			formDisabled: false,
			loading: true,
			customNamesOptions: [],
		}
	},
	computed: {
		savePossible() {
			var emptyNames = Object.keys(this.certificate.rootCert.names).filter(key => {
				var item = this.certificate.rootCert.names[key]
				return item.value === ''
			})
			return (
				this.certificate.rootCert.commonName !== ''
				&& emptyNames.length === 0
			)
		},
	},
	async mounted() {
		this.loading = false
		this.loadRootCertificate()
		this.$root.$on('afterConfigCheck', data => {
			this.cfsslBinariesOk = data.filter((o) => o.resource == 'cfssl' && o.status == 'error').length == 0
			this.cfsslConfigureOk = data.filter((o) => o.resource == 'cfssl-configure' && o.status == 'error').length == 0
		})
	},

	methods: {
		validate(id) {
			var dataset = this.rootCertDataset[id];
			var item = this.certificate.rootCert.names[id]
			if (Object.hasOwn(dataset, 'min')) {
				if (item.value.length < dataset.min) {
					item.helperText = Object.hasOwn(dataset, 'minHelper') ? dataset.minHelper : ''
					item.error = true
				} else {
					item.helperText = Object.hasOwn(this.rootCertDataset[id], 'defaultHelper')
						? this.rootCertDataset[id].defaultHelper
						: '',
					item.error = false
				}
			}
		},
		getCustomNamesOptionsById(id) {
			return this.rootCertDataset[id].label
		},
		async onOptionalAttributeSelect(selected) {
			if (Object.hasOwn(this.certificate.rootCert.names, selected.id)) {
				return
			}
			this.$set(this.certificate.rootCert.names, selected.id, {
				'id': selected.id,
				'value': '',
				'maxlength': Object.hasOwn(this.rootCertDataset[selected.id], 'max')
					? this.rootCertDataset[selected.id].max
					: '',
				'helperText': Object.hasOwn(this.rootCertDataset[selected.id], 'defaultHelper')
					? this.rootCertDataset[selected.id].defaultHelper
					: '',
			})
			for( var i = 0; i < this.customNamesOptions.length; i++) {
				if (this.customNamesOptions[i].id === selected.id) {
					this.customNamesOptions.splice(i, 1)
					break;
				}
			}
		},
		async removeOptionalAttribute(key) {
			this.customNamesOptions.push(this.rootCertDataset[key])
			delete this.certificate.rootCert.names[key]
		},
		showModal() {
			this.modal = true
		},
		closeModal() {
			this.modal = false
		},
		clearAndShowForm() {
			this.customNamesOptions = []
			Object.keys(this.rootCertDataset).forEach(key => {
				var item = this.rootCertDataset[key]
				this.customNamesOptions.push(item)
			})
			this.certificate.rootCert.commonName = ''
			this.certificate.rootCert.names = {}
			this.certificate.cfsslUri = ''
			this.certificate.configPath = ''
			this.certificate.generated = false
			this.customCfsslData = false
			this.cfsslConfigureOk = false
			this.formDisabled = false
			this.modal = false
			this.submitLabel = t('libresign', 'Generate root certificate.')
		},
		async generateCertificate() {
			this.formDisabled = true
			this.submitLabel = t('libresign', 'Generating certificate.')
			try {
				const response = await axios.post(
					generateUrl('/apps/libresign/api/0.1/admin/certificate'),
					this.getDataToSave()
				)

				if (!response.data || response.data.message) {
					throw new Error(response.data)
				}
				this.certificate = response.data.data
				this.afterCertificateGenerated()
				this.$root.$emit('configCheck');
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
			var data = {};
			Object.keys(this.certificate).forEach(rootProperty => {
				if (!Object.hasOwn(data, rootProperty)) {
					data[rootProperty] = {}
				}
				if (rootProperty === 'rootCert') {
					Object.keys(this.certificate[rootProperty]).forEach(level2 => {
						if (level2 === 'names') {
							if (!Object.hasOwn(data[rootProperty], 'names')) {
								data[rootProperty]['names'] = {}
							}
							Object.keys(this.certificate[rootProperty]['names']).forEach(name => {
								data[rootProperty]['names'][name] = {}
								data[rootProperty]['names'][name]['value'] = this.certificate[rootProperty]['names'][name]['value']
							})
						} else {
							data[rootProperty][level2] = this.certificate[rootProperty][level2]
						}
					})
				} else {
					data[rootProperty] = this.certificate[rootProperty]
				}
			})
			return data
		},

		async loadRootCertificate() {
			this.formDisabled = true
			try {
				const response = await axios.get(
					generateUrl('/apps/libresign/api/0.1/admin/certificate'),
				)
				if (!response.data || response.data.message) {
					throw new Error(response.data)
				}
				this.certificate = response.data
				this.cfsslConfigureOk = this.certificate.generated
				this.customCfsslData = this.certificate.cfsslUri.length > 0 || this.certificate.configPath.length > 0
				if (!Object.hasOwn(this.certificate.rootCert, 'commonName')) {
					this.$set(this.certificate.rootCert, 'commonName', '')
				}
				Object.keys(this.rootCertDataset).forEach(key => {
					var item = this.rootCertDataset[key]
					if (!Object.hasOwn(this.certificate.rootCert.names, key)) {
						this.customNamesOptions.push(item)
					} else {
						this.validate(key)
					}
				})
				if (response.data.rootCert.commonName
				&& response.data.country
				&& response.data.organization
				&& response.data.organizationUnit
				&& this.cfsslConfigureOk
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
			this.certificate.generated = true;
		},
	},
}
</script>
<style lang="scss" scoped>
#formRootCertificate{
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
	#formRootCertificate{
		width: 100%;
	}
}

</style>
