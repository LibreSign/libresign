<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection :name="name" :description="description">
		<NcSelect :key="idKey"
			v-model="selected"
			label="displayname"
			:no-wrap="false"
			:aria-label-combobox="description"
			:close-on-select="false"
			:disabled="loading"
			:loading="loading"
			required
			:options="hashes"
			:show-no-options="false"
			@update:modelValue="saveSignatureHash" />
	</NcSettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { generateOcsUrl } from '@nextcloud/router'

import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import '@nextcloud/password-confirmation/dist/style.css'

export default {
	name: 'SignatureHashAlgorithm',
	components: {
		NcSettingsSection,
		NcSelect,
	},

	data: () => ({
		name: t('libresign', 'Signature hash algorithm'),
		description: t('libresign', 'Hash algorithm used for signature.'),
		selected: [],
		hashes: ['SHA1', 'SHA256', 'SHA384', 'SHA512', 'RIPEMD160'],
		loading: false,
		idKey: 0,
	}),

	mounted() {
		this.getData()
	},

	methods: {
		async getData() {
			this.loading = true
			const response = await axios.get(
				generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/signature_hash_algorithm'),
			)
			this.selected = this.hashes.includes(response.data.ocs.data.data)
				? response.data.ocs.data.data
				: 'SHA256'
			this.loading = false
		},

		async saveSignatureHash() {
			await confirmPassword()

			const selected = this.hashes.includes(this.selected) ? this.selected : 'SHA256'
			OCP.AppConfig.setValue('libresign', 'signature_hash_algorithm', selected)
			this.idKey += 1
		},
	},

}
</script>
