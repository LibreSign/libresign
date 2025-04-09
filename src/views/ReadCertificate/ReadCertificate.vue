<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="signMethodsStore.modal.readCertificate"
		:name="t('libresign', 'Certificate data')"
		:size="size"
		is-form
		@submit.prevent="send()"
		@closing="onClose">
		<NcNoteCard v-if="error" type="error">
			<p>{{ error }}</p>
		</NcNoteCard>
		<CertificateContent v-if="Object.keys(certificate).length"
			:certificate="certificate" />
		<div v-else class="container">
			<div class="input-group">
				<NcPasswordField v-model="password"
					:disabled="hasLoading"
					:label="t('libresign', 'Certificate password')"
					:placeholder="t('libresign', 'Certificate password')" />
			</div>
		</div>
		<template v-if="Object.keys(certificate).length === 0" #actions>
			<NcButton :disabled="hasLoading"
				type="submit"
				variant="primary"
				@click="send()">
				<template #icon>
					<NcLoadingIcon v-if="hasLoading" :size="20" />
				</template>
				{{ t('libresign', 'Confirm') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'

import CertificateContent from './CertificateContent.vue'

import { useSignMethodsStore } from '../../store/signMethods.js'

export default {
	name: 'ReadCertificate',
	components: {
		CertificateContent,
		NcDialog,
		NcPasswordField,
		NcButton,
		NcNoteCard,
		NcLoadingIcon,
	},
	setup() {
		const signMethodsStore = useSignMethodsStore()
		return { signMethodsStore }
	},
	data() {
		return {
			hasLoading: false,
			password: '',
			certificate: {},
			error: '',
			size: 'small',
		}
	},
	mounted() {
		this.reset()
	},
	methods: {
		reset() {
			this.password = ''
			this.certificate = {}
			this.error = ''
			this.size = 'small'
		},
		async send() {
			this.hasLoading = true
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/account/pfx/read'), {
				password: this.password,
			})
				.then(({ data }) => {
					this.certificate = data.ocs.data
					this.size = 'large'
					this.error = ''
				})
				.catch(({ response }) => {
					if (response?.data?.ocs?.data?.message?.length > 0) {
						this.error = response.data.ocs.data.message
					} else {
						this.error = t('libresign', 'Invalid password')
					}
				})
			this.hasLoading = false
		},
		onClose() {
			this.signMethodsStore.closeModal('readCertificate')
			this.reset()
		},
	},
}
</script>

<style lang="scss" scoped>
form{
	display: flex;
	flex-direction: column;
	width: 100%;
	max-width: 100%;
	justify-content: center;
	align-items: center;
	text-align: center;
	header{
		font-weight: bold;
		font-size: 20px;
		margin-bottom: 12px;
		line-height: 30px;
		color: var(--color-text-light);
	}
}

.container {
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 20px;
	gap: 4px 0;
}

.input-group{
	justify-content: space-between;
	display: flex;
	flex-direction: column;
	width: 100%;
}
</style>
