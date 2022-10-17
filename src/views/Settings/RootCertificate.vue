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
	<NcSettingsSection :title="title" :description="description"
		v-if=cfsslOk>
		<div id="tableRootCertificate" class="form-libresign" v-if="certificate.generated">
			<table class="grid">
				<tbody>
					<tr>
						<td>{{ t('libresign', 'Name (CN)') }}</td>
						<td>{{ certificate.commonName }}</td>
					</tr>
					<tr>
						<td>{{ t('libresign', 'Country (C)') }}</td>
						<td>{{ certificate.country }}</td>
					</tr>
					<tr>
						<td>{{ t('libresign', 'Organization (O)') }}</td>
						<td>{{ certificate.organization }}</td>
					</tr>
					<tr>
						<td>{{ t('libresign', 'Organization Unit (OU)') }}</td>
						<td>{{ certificate.organizationUnit }}</td>
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
				@click="showModal">
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
		<div v-else id="formRootCertificate" class="form-libresign">
			<div class="form-group">
				<label for="commonName" class="form-heading--required">{{ t('libresign', 'Name (CN)') }}</label>
				<input id="commonName"
					ref="commonName"
					v-model="certificate.commonName"
					type="text"
					:disabled="formDisabled">
			</div>
			<div class="form-group">
				<label for="country" class="form-heading--required">{{ t('libresign', 'Country (C)') }}</label>
				<input id="country"
					ref="country"
					v-model="certificate.country"
					type="text"
					:disabled="formDisabled">
			</div>
			<div class="form-group">
				<label for="organization" class="form-heading--required">{{ t('libresign', 'Organization (O)') }}</label>
				<input id="organization"
					ref="organization"
					v-model="certificate.organization"
					type="text"
					:disabled="formDisabled">
			</div>
			<div class="form-group">
				<label for="organizationUnit" class="form-heading--required">{{ t('libresign', 'Organization Unit (OU)') }}</label>
				<input id="organizationUnit"
					ref="organizationUnit"
					v-model="certificate.organizationUnit"
					type="text"
					:disabled="formDisabled">
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
				<input id="cfsslUri"
					ref="cfsslUri"
					v-model="certificate.cfsslUri"
					type="text"
					:placeholder="t('libresign', 'Not mandatory, don\'t fill to use default value.')"
					:disabled="formDisabled">
			</div>
			<div class="form-group" v-if="customCfsslData">
				<label for="configPath">{{ t('libresign', 'Config path') }}</label>
				<input id="configPath"
					ref="configPath"
					v-model="certificate.configPath"
					type="text"
					:placeholder="t('libresign', 'Not mandatory, don\'t fill to use default value.')"
					:disabled="formDisabled">
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
import NcModal from '@nextcloud/vue/dist/Components/NcModal'
import NcButton from '@nextcloud/vue/dist/Components/NcButton'
import '@nextcloud/dialogs/styles/toast.scss'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'AdminFormLibresign',
	components: {
		NcSettingsSection,
		NcCheckboxRadioSwitch,
		NcModal,
		NcButton,
	},
	data() {
		return {
			cfsslOk: false,
			modal: false,
			certificate: {
				commonName: '',
				country: '',
				organization: '',
				organizationUnit: '',
				cfsslUri: '',
				configPath: '',
			},
			customCfsslData: false,
			title: t('libresign', 'Root certificate data.'),
			description: t('libresign', 'To generate new signatures, you must first generate the root certificate.'),
			submitLabel: t('libresign', 'Generate root certificate.'),
			formDisabled: false,
			loading: true,
		}
	},
	computed: {
		savePossible() {
			return (
				this.certificate
								&& this.certificate.commonName !== ''
								&& this.certificate.country !== ''
								&& this.certificate.organization !== ''
								&& this.certificate.organizationUnit !== ''
			)
		},
	},
	async mounted() {
		this.loading = false
		this.loadRootCertificate()
		this.$root.$on('afterConfigCheck', data => {
			this.cfsslOk = data.filter((o) => o.resource == 'cfssl' && o.status == 'error').length == 0
		})
	},

	methods: {
		showModal() {
			this.modal = true
		},
		closeModal() {
			this.modal = false
		},
		clearAndShowForm() {
			this.certificate.commonName = ''
			this.certificate.country = ''
			this.certificate.organization = ''
			this.certificate.organizationUnit = ''
			this.certificate.cfsslUri = ''
			this.certificate.configPath = ''
			this.certificate.generated = false
			this.customCfsslData = false
			this.submitLabel = t('libresign', 'Generate root certificate.')
		},
		async generateCertificate() {
			this.formDisabled = true
			this.submitLabel = t('libresign', 'Generating certificate.')
			try {
				const response = await axios.post(
					generateUrl('/apps/libresign/api/0.1/admin/certificate'),
					this.certificate
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
				this.cfsslOk = this.certificate.generated
				this.customCfsslData = this.certificate.cfsslUri.length > 0 || this.certificate.configPath.length > 0
				if (response.data.commonName
				&& response.data.country
				&& response.data.organization
				&& response.data.organizationUnit
				&& this.cfsslOk
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

.form-group > input[type='text'] {
	width: 100%;
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
