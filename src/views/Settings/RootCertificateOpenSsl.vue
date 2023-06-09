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
	<NcSettingsSection v-if="isThisEngine"
		:title="title"
		:description="description"
		:doc-url="docUrl">
		<div v-if="configureOk" id="tableRootCertificateOpenSsl" class="form-libresign">
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
						<td>{{ t('libresign', 'Config path') }}</td>
						<td>{{ certificate.configPath }}</td>
					</tr>
				</tbody>
			</table>
			<NcButton @click="showModal">
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
		<div v-else id="formRootCertificateOpenSsl" class="form-libresign">
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
				<NcPopover container="body" :popper-hide-triggers="(triggers) => [...triggers, 'click']">
					<template #trigger>
						<NcButton :disabled="customNamesOptions.length === 0">
							{{ t('libresign', 'Select a custom name') }}
						</NcButton>
					</template>
					<template #default>
						<ul style="width: 350px;">
							<div v-for="option in customNamesOptions" :key="option.id">
								<NcListItem :title="option.label"
									@click="onOptionalAttributeSelect(option)">
									<template #subname>
										{{ option.label }}
									</template>
								</NcListItem>
							</div>
						</ul>
					</template>
				</NcPopover>
				<div v-for="(customName, key) in certificate.rootCert.names" :key="customName.id" class="customNames">
					<label :for="customName.id" class="form-heading--required">
						{{ getCustomNamesOptionsById(customName.id) }} ({{ customName.id }})
					</label>
					<div class="item">
						<NcTextField v-if="customName"
							:id="customName.id"
							:value.sync="customName.value"
							:success="typeof customName.error === 'boolean' && !customName.error"
							:error="customName.error"
							:maxlength="customName.maxlength"
							:helper-text="customName.helperText"
							:disabled="formDisabled"
							@update:value="validate(customName.id)" />
						<NcButton :aria-label="t('settings', 'Remove custom name entry from root certificate')"
							@click="removeOptionalAttribute(key)">
							<template #icon>
								<Delete :size="20" />
							</template>
						</NcButton>
					</div>
				</div>
			</div>
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
					:value.sync="certificate.configPath"
					:label-outside="true"
					:placeholder="t('libresign', 'Not mandatory, don\'t fill to use default value.')"
					:disabled="formDisabled" />
			</div>
			<NcButton :disabled="formDisabled || !savePossible"
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
import NcPopover from '@nextcloud/vue/dist/Components/NcPopover.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import Delete from 'vue-material-design-icons/Delete.vue'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { subscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'RootCertificateOpenSsl',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
		NcModal,
		NcButton,
		NcTextField,
		NcPopover,
		NcListItem,
		Delete,
	},
	data() {
		return {
			configureOk: false,
			isThisEngine: loadState('libresign', 'certificate_engine') === 'openssl',
			modal: false,
			certificate: {
				rootCert: {
					commonName: '',
					names: {},
				},
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
			customData: false,
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
			const emptyNames = Object.keys(this.certificate.rootCert.names).filter(key => {
				const item = this.certificate.rootCert.names[key]
				return item.value === ''
			})
			return (
				this.certificate.rootCert.commonName !== ''
				&& emptyNames.length === 0
			)
		},
	},
	async mounted() {
		if (this.isThisEngine) {
			this.loadRootCertificate()
		}
		this.loading = false
		subscribe('libresign:certificate-engine:changed', this.changeEngine)
		this.$root.$on('after-config-check', data => {
			this.configureOk = data.filter((o) => o.resource === 'openssl-configure' && o.status === 'error').length === 0
		})
	},

	methods: {
		changeEngine(engine) {
			this.isThisEngine = engine === 'openssl'
			if (this.isThisEngine) {
				this.loadRootCertificate()
			}
		},
		validate(id) {
			const dataset = this.rootCertDataset[id]
			const item = this.certificate.rootCert.names[id]
			if (Object.hasOwn(dataset, 'min')) {
				if (item.value.length < dataset.min) {
					item.helperText = Object.hasOwn(dataset, 'minHelper') ? dataset.minHelper : ''
					item.error = true
				} else {
					item.helperText = Object.hasOwn(this.rootCertDataset[id], 'defaultHelper')
						? this.rootCertDataset[id].defaultHelper
						: ''
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
				id: selected.id,
				value: '',
				maxlength: Object.hasOwn(this.rootCertDataset[selected.id], 'max')
					? this.rootCertDataset[selected.id].max
					: '',
				helperText: Object.hasOwn(this.rootCertDataset[selected.id], 'defaultHelper')
					? this.rootCertDataset[selected.id].defaultHelper
					: '',
			})
			for (let i = 0; i < this.customNamesOptions.length; i++) {
				if (this.customNamesOptions[i].id === selected.id) {
					this.customNamesOptions.splice(i, 1)
					break
				}
			}
		},
		async removeOptionalAttribute(key) {
			// this.customNamesOptions.push(this.rootCertDataset[key])
			console.log(this.certificate.rootCert.names)
			// <div v-for="(customName, key) in certificate.rootCert.names" :key="customName.id" class="customNames">
			// this.certificate.rootCert.names = this.certificate.rootCert.names.filter(item => item.id !== key)
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
				const item = this.rootCertDataset[key]
				// TODO: remove  use array push
				this.customNamesOptions.push(item)
			})
			this.certificate.rootCert.commonName = ''
			this.certificate.rootCert.names = {}
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
					generateOcsUrl('/apps/libresign/api/v1/admin/certificate/openssl'),
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
			const data = {}
			Object.keys(this.certificate).forEach(rootProperty => {
				if (!Object.hasOwn(data, rootProperty)) {
					data[rootProperty] = {}
				}
				if (rootProperty === 'rootCert') {
					Object.keys(this.certificate[rootProperty]).forEach(level2 => {
						if (level2 === 'names') {
							if (!Object.hasOwn(data[rootProperty], 'names')) {
								data[rootProperty].names = {}
							}
							Object.keys(this.certificate[rootProperty].names).forEach(name => {
								data[rootProperty].names[name] = {}
								data[rootProperty].names[name].value = this.certificate[rootProperty].names[name].value
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
					generateOcsUrl('/apps/libresign/api/v1/admin/certificate'),
				)
				if (!response.data || response.data.message) {
					throw new Error(response.data)
				}
				this.certificate = response.data
				this.configureOk = this.certificate.generated
				this.customData = this.certificate.configPath.length > 0
				if (!Object.hasOwn(this.certificate.rootCert, 'commonName')) {
					this.$set(this.certificate.rootCert, 'commonName', '')
				}
				Object.keys(this.rootCertDataset).forEach(key => {
					const item = this.rootCertDataset[key]
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
	#formRootCertificateOpenSsl{
		width: 100%;
	}
}

</style>
