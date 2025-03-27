<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="name" :description="description">
		{{ t('libresign', 'You can use the following variables in your signature text:') }}
		<ul class="available-variables">
			<li v-for="(availableDescription, availableName) in availableVariables"
				:key="availableName"
				:class="{rtl: isRTLDirection}">
				<strong :class="{rtl: isRTLDirection}">{{ availableName }}:</strong>
				<span>{{ availableDescription }}</span>
			</li>
		</ul>
		<div class="content">
			<NcTextArea :value.sync="inputValue"
				:label="t('libresign', 'Signature text template')"
				:placeholder="t('libresign', 'Signature text template')"
				:spellcheck="false"
				:success="showSuccess"
				resize="vertical"
				@keydown.enter="save"
				@blur="save" />
			<NcTextField :value.sync="fontSize"
				:label="t('libresign', 'Font size')"
				:placeholder="t('libresign', 'Font size')"
				type="number"
				:min="0.1"
				:max="30"
				:step="0.01"
				:spellcheck="false"
				:success="showSuccess"
				@keydown.enter="save"
				@blur="save" />
			<NcNoteCard v-if="errorMessage"
				type="error"
				:show-alert="true">
				<p>{{ errorMessage }}</p>
			</NcNoteCard>
			<div class="text-pre-line">
				{{ parsed }}
			</div>
		</div>
	</NcSettingsSection>
</template>
<script>
import debounce from 'debounce'

import axios from '@nextcloud/axios'
import { translate as t, isRTL } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'

import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'

export default {
	name: 'SignatureTextTemplate',
	components: {
		NcSettingsSection,
		NcNoteCard,
		NcTextArea,
		NcTextField,
	},
	data() {
		return {
			name: t('libresign', 'Signature text template'),
			description: t('libresign', 'This template will be mixed to signature.'),
			signatureTextTemplate: '',
			showSuccess: false,
			errorMessage: '',
			parsed: '',
			fontSize: 6,
			isRTLDirection: isRTL(),
			availableVariables: {
				'{{SignerName}}': t('libresign', 'Name of the person signing'),
				'{{DocumentUUID}}': t('libresign', 'Unique identifier of the signed document'),
				'{{IssuerCommonName}}': t('libresign', 'Name of the certificate issuer used for the signature'),
				'{{LocalSignerSignatureDate}}': t('libresign', 'Date and time when the user initiated the signing process (in their local time zone)'),
				'{{ServerSignatureDate}}': t('libresign', 'Date and time when the signature was applied on the server'),
			},
		}
	},
	computed: {
		inputValue: {
			get() {
				return this.signatureTextTemplate
			},
			set(value) {
				this.signatureTextTemplate = value
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
					if (data.ocs.data.fontSize !== this.fontSize) {
						this.fontSize = data.ocs.data.fontSize
					}
				})
				.catch(({ response }) => {
					this.errorMessage = response.data.ocs.data.error
					this.parsed = ''
				})
		},
		async save() {
			this.showSuccess = false
			this.errorMessage = ''
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/admin/signature-text'), {
				template: this.signatureTextTemplate,
				fontSize: this.fontSize,
			})
				.then(({ data }) => {
					this.parsed = data.ocs.data.parsed
					if (data.ocs.data.fontSize !== this.fontSize) {
						this.fontSize = data.ocs.data.fontSize
					}
					this.showSuccess = true
					setTimeout(() => { this.showSuccess = false }, 2000)
				})
				.catch(({ response }) => {
					this.errorMessage = response.data.ocs.data.error
					this.parsed = ''
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

.available-variables {
	margin-bottom: 1em;
}

.rtl {
	direction: rtl;
	text-align: right;
}
</style>
