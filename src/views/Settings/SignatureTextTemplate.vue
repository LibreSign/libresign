<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="name" :description="description">
		<div class="content">
			<NcTextArea :value.sync="inputValue"
				:placeholder="t('libresign', 'Signature text template')"
				:success="showSuccess"
				resize="vertical"
				@keydown.enter="save"
				@blur="save" />
			<div class="text-pre-line">
				{{ parsed }}
			</div>
		</div>
	</NcSettingsSection>
</template>
<script>
import debounce from 'debounce'

import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'

export default {
	name: 'SignatureTextTemplate',
	components: {
		NcSettingsSection,
		NcTextArea,
	},
	data() {
		return {
			name: t('libresign', 'Signature text template'),
			description: t('libresign', 'This template will be mixed to signature.'),
			signatureTextTemplate: '',
			showSuccess: false,
			parsed: '',
		}
	},
	computed: {
		inputValue: {
			get() {
				return this.signatureTextTemplate
			},
			set(value) {
				this.signatureTextTemplate = value.trim()
				this.debouncePropertyChange()
			},
		},
		debouncePropertyChange() {
			return debounce(async function() {
				await this.save()
			}, 1000)
		},
	},
	created() {
		this.getData()
	},
	methods: {
		async getData() {
			await axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/signature_text_template'))
				.then(({ data }) => {
					this.signatureTextTemplate = data.ocs.data.data
					return axios.get(generateOcsUrl('/apps/libresign/api/v1/admin/signature-text'))
				})
				.then(({ data }) => {
					this.parsed = data.ocs.data.parsed
				})
		},
		async save() {
			this.showSuccess = false
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/signature-text'), { template: this.signatureTextTemplate })
				.then(({ data }) => {
					this.parsed = data.ocs.data.parsed
					this.showSuccess = true
					setTimeout(() => { this.showSuccess = false }, 2000)
				})
		},
	},
}
</script>
<style scoped>
.content{
	display: flex;
	flex-direction: column;
}
.text-pre-line {
	white-space: pre-line;
}
</style>
