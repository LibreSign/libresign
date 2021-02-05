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
	<div id="formLibresign" class="form-libresign">
		<div class="form-group">
			<label for="commonName">{{ t('libresign', 'Nome (CN)') }}</label>
			<input
				id="commonName"
				ref="commonName"
				v-model="certificate.commonName"
				type="text"
				:disabled="formDisabled">
		</div>
		<div class="form-group">
			<label for="country">{{ t('libresign', 'País (C)') }}</label>
			<input
				id="country"
				ref="country"
				v-model="certificate.country"
				type="text"
				:disabled="formDisabled">
		</div>
		<div class="form-group">
			<label for="organization">{{ t('libresign', 'Organização (O)') }}</label>
			<input
				id="organization"
				ref="organization"
				v-model="certificate.organization"
				type="text"
				:disabled="formDisabled">
		</div>
		<div class="form-group">
			<label for="organizationUnit">{{ t('libresign', 'Unidade da organização (OU)') }}</label>
			<input
				id="organizationUnit"
				ref="organizationUnit"
				v-model="certificate.organizationUnit"
				type="text"
				:disabled="formDisabled">
		</div>
		<div class="form-group">
			<label for="cfsslUri">{{ t('libresign', 'CFSSL API Uri') }}</label>
			<input
				id="cfsslUri"
				ref="cfsslUri"
				v-model="certificate.cfsslUri"
				type="text"
				:disabled="formDisabled">
		</div>
		<div class="form-group">
			<label for="configPath">{{ t('libresign', 'Config path') }}</label>
			<input
				id="configPath"
				ref="configPath"
				v-model="certificate.configPath"
				type="text"
				:disabled="formDisabled">
		</div>
		<input
			type="button"
			class="primary"
			:value="submitLabel"
			:disabled="formDisabled || !savePossible"
			@click="generateCertificate">
	</div>
</template>

<script>
import '@nextcloud/dialogs/styles/toast.scss'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'AdminFormLibresign',
	components: {
	},
	data() {
		return {
			certificate: {
				commonName: '',
				country: '',
				organization: '',
				organizationUnit: '',
				cfsslUri: '',
				configPath: '',
			},
			submitLabel: t('libresign', 'Gerar Certificado Raiz'),
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
                && this.certificate.cfsslUri !== ''
                && this.certificate.configPath !== ''
			)
		},
	},
	async mounted() {
		this.loading = false
		this.loadRootCertificate()
	},

	methods: {
		async generateCertificate() {
			this.formDisabled = true
			this.submitLabel = 'Gerando Certificado...'
			try {
				const response = await axios.post(
					generateUrl('/apps/libresign/api/0.1/admin/certificate'),
					this.certificate
				)

				if (!response.data || response.data.message) {
					throw new Error(response.data)
				}
				this.submitLabel = 'Certificado gerado!'

				return
			} catch (e) {
				console.error(e)
				showError(t('libresign', 'Não foi possivel gerar certificado'))
				this.submitLabel = 'Gerar Certificado Raiz'

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

				if (response.data.commonName
				&& response.data.country
				&& response.data.organization
				&& response.data.organizationUnit
				&& response.data.cfsslUri
				&& response.data.configPath
				) {
					this.certificate = response.data
					this.submitLabel = 'Certificado gerado!'

					return
				}
			} catch (e) {
				console.error(e)
			}
			this.formDisabled = false
		},
	},
}
</script>
<style>
#formLibresign{
	width: 60%;
	text-align: left;
	margin: 20px;
}

.form-group > input[type='text'] {
	width: 100%;
}

</style>
