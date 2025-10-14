<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection :name="name" :description="description">
		<NcCheckboxRadioSwitch type="switch"
			:checked.sync="enabled"
			@update:checked="toggleTsa">
			{{ t('libresign', 'Use timestamp server') }}
		</NcCheckboxRadioSwitch>

		<div v-if="enabled" class="tsa-config-container">
			<NcTextField :value="tsa_url"
				:label="t('libresign', 'TSA Server URL')"
				:placeholder="t('libresign', 'Enter the timestamp server URL')"
				:disabled="loading"
				:loading="loading"
				@update:value="updateTsaUrl" />

			<NcTextField :value="tsa_policy_oid"
				:label="t('libresign', 'TSA Policy OID')"
				:placeholder="t('libresign', 'Enter the policy OID (optional')"
				:disabled="loading"
				:loading="loading"
				:helper-text="t('libresign', 'Example: 1.2.3.4.5 or leave empty for server default')"
				@update:value="updateTsaPolicyOid" />
		</div>
	</NcSettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { generateOcsUrl } from '@nextcloud/router'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import '@nextcloud/password-confirmation/dist/style.css'

export default {
	name: 'TSA',
	components: {
		NcCheckboxRadioSwitch,
		NcSettingsSection,
		NcTextField,
	},

	data: () => ({
		name: t('libresign', 'Timestamp Authority (TSA)'),
		description: t('libresign', 'Timestamp Authority (TSA) settings for digitally signing documents.'),
		enabled: false,
		tsa_url: '',
		tsa_policy_oid: '',
		loading: false,
	}),

	mounted() {
		this.getData()
	},
	methods: {
		updateTsaUrl(value) {
			this.tsa_url = value
			this.debouncedSaveTsaUrl()
		},

		updateTsaPolicyOid(value) {
			this.tsa_policy_oid = value
			this.debouncedSaveTsaPolicyOid()
		},

		async getData() {
			this.loading = true
			try {
				const [tsaUrlResponse, tsaPolicyResponse] = await Promise.all([
					axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/tsa_url')),
					axios.get(generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/libresign/tsa_policy_oid'))
				])

				this.tsa_url = tsaUrlResponse?.data?.ocs?.data?.data ?? ''
				this.tsa_policy_oid = tsaPolicyResponse?.data?.ocs?.data?.data ?? ''
				this.enabled = this.tsa_url.length > 0
			} catch (error) {
				console.error('Error loading TSA configuration:', error)
			} finally {
				this.loading = false
			}
		},

		async toggleTsa() {
			if (!this.enabled) {
				await this.clearTsaConfig()
			} else if (!this.tsa_url) {
				this.tsa_url = 'https://freetsa.org/tsr'
			}
		},

		async saveTsaUrl() {
			if (!this.tsa_url || !this.isValidUrl(this.tsa_url)) {
				this.$toast.error(t('libresign', 'Please enter a valid URL'))
				return
			}

			await confirmPassword()
			this.loading = true

			try {
				await OCP.AppConfig.setValue('libresign', 'tsa_url', this.tsa_url)
			} catch (error) {
				this.$toast.error(t('libresign', 'Error saving TSA URL'))
				console.error('Error saving TSA URL:', error)
			} finally {
				this.loading = false
			}
		},

		async saveTsaPolicyOid() {
			if (this.tsa_policy_oid && !this.isValidOid(this.tsa_policy_oid)) {
				this.$toast.error(t('libresign', 'Please enter a valid OID format (e.g., 1.2.3.4.5)'))
				return
			}

			await confirmPassword()
			this.loading = true

			try {
				if (this.tsa_policy_oid) {
					await OCP.AppConfig.setValue('libresign', 'tsa_policy_oid', this.tsa_policy_oid)
				} else {
					await OCP.AppConfig.deleteKey('libresign', 'tsa_policy_oid')
				}
			} catch (error) {
				this.$toast.error(t('libresign', 'Error saving TSA Policy OID'))
				console.error('Error saving TSA Policy OID:', error)
			} finally {
				this.loading = false
			}
		},

		async clearTsaConfig() {
			await confirmPassword()
			this.loading = true

			try {
				await Promise.all([
					OCP.AppConfig.deleteKey('libresign', 'tsa_url'),
					OCP.AppConfig.deleteKey('libresign', 'tsa_policy_oid')
				])
				this.tsa_url = ''
				this.tsa_policy_oid = ''
				this.$toast.success(t('libresign', 'TSA configuration cleared successfully'))
			} catch (error) {
				this.$toast.error(t('libresign', 'Error clearing TSA configuration'))
				console.error('Error clearing TSA configuration:', error)
			} finally {
				this.loading = false
			}
		},

		isValidUrl(string) {
			try {
				const url = new URL(string)
				return url.protocol === 'http:' || url.protocol === 'https:'
			} catch (_) {
				return false
			}
		},

		isValidOid(oid) {
			const oidRegex = /^[0-9]+(\.[0-9]+)*$/
			return oidRegex.test(oid.trim())
		},
	},

	created() {
		const debounce = (func, wait) => {
			let timeout
			return function executedFunction(...args) {
				const later = () => {
					clearTimeout(timeout)
					func(...args)
				}
				clearTimeout(timeout)
				timeout = setTimeout(later, wait)
			}
		}

		this.debouncedSaveTsaUrl = debounce(this.saveTsaUrl, 1000)
		this.debouncedSaveTsaPolicyOid = debounce(this.saveTsaPolicyOid, 1000)
	},

}
</script>

<style scoped>
.tsa-config-container {
	margin-top: 16px;
}
</style>
