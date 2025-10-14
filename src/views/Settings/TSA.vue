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
				:error="errors.tsa_url"
				:helper-text="getHelperText('tsa_url')"
				@update:value="(value) => updateField('tsa_url', value)" />

			<NcTextField :value="tsa_policy_oid"
				:label="t('libresign', 'TSA Policy OID')"
				:placeholder="t('libresign', 'Enter the policy OID (optional)')"
				:disabled="loading"
				:loading="loading"
				:error="errors.tsa_policy_oid"
				:helper-text="getHelperText('tsa_policy_oid')"
				@update:value="(value) => updateField('tsa_policy_oid', value)" />

			<NcSelect v-model="selectedAuthType"
				:options="authOptions"
				input-label="TSA Authentication"
				:disabled="loading"
				:loading="loading"
				clearable />

			<template v-if="tsa_auth_type === 'basic'">
				<NcTextField :value="tsa_username"
					:label="t('libresign', 'TSA Username')"
					:placeholder="t('libresign', 'Enter the TSA username')"
					:disabled="loading"
					:loading="loading"
					@update:value="(value) => updateField('tsa_username', value)" />

				<NcPasswordField :value="tsa_password"
					:label="t('libresign', 'TSA Password')"
					:placeholder="t('libresign', 'Enter the TSA password')"
					:disabled="loading"
					:loading="loading"
					@update:value="(value) => updateField('tsa_password', value)" />
			</template>
		</div>
	</NcSettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { generateOcsUrl } from '@nextcloud/router'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import '@nextcloud/password-confirmation/dist/style.css'

export default {
	name: 'TSA',
	components: {
		NcCheckboxRadioSwitch,
		NcPasswordField,
		NcSelect,
		NcSettingsSection,
		NcTextField,
	},

	data: () => ({
		name: t('libresign', 'Timestamp Authority (TSA)'),
		description: t('libresign', 'Timestamp Authority (TSA) settings for digitally signing documents.'),
		enabled: false,
		tsa_url: '',
		tsa_policy_oid: '',
		tsa_auth_type: 'none',
		tsa_username: '',
		tsa_password: '',
		loading: false,
		errors: {
			tsa_url: false,
			tsa_policy_oid: false,
		},
		authOptions: [
			{
				id: 'none',
				label: t('libresign', 'Without authentication'),
			},
			{
				id: 'basic',
				label: t('libresign', 'Username / Password'),
			},
		],
	}),

	computed: {
		selectedAuthType: {
			get() {
				return this.authOptions.find(option => option.id === this.tsa_auth_type) || this.authOptions[0]
			},
			set(value) {
				this.tsa_auth_type = value?.id || 'none'
				this.debouncedSaveField('tsa_auth_type')
			}
		}
	},

	mounted() {
		this.getData()
	},
	methods: {
		updateField(field, value) {
			this[field] = value
			this.validateField(field, value)
			this.debouncedSaveField(field)
		},



		validateField(field, value) {
			const validators = {
				tsa_url: () => !!value && !this.isValidUrl(value),
				tsa_policy_oid: () => !!value && !this.isValidOid(value)
			}

			if (validators[field]) {
				this.errors[field] = validators[field]()
			}
		},

		getHelperText(field) {
			const helperTexts = {
				tsa_url: {
					error: t('libresign', 'Please enter a valid URL'),
					normal: t('libresign', 'Format: https://example.com/tsa')
				},
				tsa_policy_oid: {
					error: t('libresign', 'Please enter a valid OID format (e.g., 1.2.3.4.5)'),
					normal: t('libresign', 'Example: 1.2.3.4.5 or leave empty for server default')
				}
			}

			const config = helperTexts[field]
			return config ? (this.errors[field] ? config.error : config.normal) : ''
		},

		async getData() {
			this.loading = true
			try {
				const fields = ['tsa_url', 'tsa_policy_oid', 'tsa_auth_type', 'tsa_username', 'tsa_password']
				const responses = await Promise.all(
					fields.map(field => axios.get(generateOcsUrl(`/apps/provisioning_api/api/v1/config/apps/libresign/${field}`)))
				)

				fields.forEach((field, index) => {
					const defaultValue = field === 'tsa_auth_type' ? 'none' : ''
					this[field] = responses[index]?.data?.ocs?.data?.data ?? defaultValue
				})

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

		async saveField(field) {
			if (this.errors[field]) {
				return
			}
			if (field === 'tsa_url' && !this.tsa_url) {
				return
			}
			if ((field === 'tsa_username' || field === 'tsa_password') && this.tsa_auth_type !== 'basic') {
				return
			}

			await confirmPassword()
			this.loading = true

			try {
				const value = this[field]

				if (field === 'tsa_auth_type') {
					await OCP.AppConfig.setValue('libresign', field, value)
					if (value === 'none') {
						await Promise.all([
							OCP.AppConfig.deleteKey('libresign', 'tsa_username'),
							OCP.AppConfig.deleteKey('libresign', 'tsa_password')
						])
						this.tsa_username = ''
						this.tsa_password = ''
					}
				} else {
					if (value) {
						await OCP.AppConfig.setValue('libresign', field, value)
					} else {
						await OCP.AppConfig.deleteKey('libresign', field)
					}
				}
			} catch (error) {
				console.error(`Error saving ${field}:`, error)
			} finally {
				this.loading = false
			}
		},

		async clearTsaConfig() {
			await confirmPassword()
			this.loading = true

			try {
				const fields = ['tsa_url', 'tsa_policy_oid', 'tsa_auth_type', 'tsa_username', 'tsa_password']
				await Promise.all(fields.map(field => OCP.AppConfig.deleteKey('libresign', field)))

				fields.forEach(field => {
					this[field] = field === 'tsa_auth_type' ? 'none' : ''
				})
			} catch (error) {
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

		this.debouncedSaveField = debounce((field) => this.saveField(field), 1000)
	},

}
</script>

<style scoped>
.tsa-config-container {
	margin-top: 16px;
}
</style>
