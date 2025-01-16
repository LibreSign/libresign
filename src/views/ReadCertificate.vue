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
		<table v-if="Object.keys(certificateData).length">
			<thead>
				<tr>
					<th colspan="2">
						{{ t('libresign', 'Issuer of certificate') }}
					</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="(value, customName) in certificateData.issuer" :key="customName">
					<td>{{ getLabelFromId(customName) }}</td>
					<td>{{ value }}</td>
				</tr>
			</tbody>
			<thead>
				<tr>
					<th colspan="2">
						{{ t('libresign', 'Owner of certificate') }}
					</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="(value, customName) in certificateData.subject" :key="customName">
					<td>{{ getLabelFromId(customName) }}</td>
					<td>{{ value }}</td>
				</tr>
			</tbody>
			<thead>
				<tr>
					<th colspan="2">
						{{ t('libresign', 'Validate') }}
					</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>From</td>
					<td>{{ certificateData.validate.from }}</td>
				</tr>
				<tr>
					<td>To</td>
					<td>{{ certificateData.validate.to }}</td>
				</tr>
			</tbody>
			<thead>
				<tr>
					<th colspan="2">
						{{ t('libresign', 'Extra information') }}
					</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>Name</td>
					<td>{{ certificateData.name }}</td>
				</tr>
				<tr v-for="(value, name) in certificateData.extensions" :key="name">
					<td>{{ name }}</td>
					<td>{{ value }}</td>
				</tr>
			</tbody>
		</table>
		<div v-else class="container">
			<div class="input-group">
				<NcPasswordField v-model="password"
					:disabled="hasLoading"
					:label="t('libresign', 'Certificate password')"
					:placeholder="t('libresign', 'Certificate password')" />
			</div>
		</div>
		<template v-if="Object.keys(certificateData).length === 0" #actions>
			<NcButton :disabled="hasLoading"
				native-type="submit"
				type="primary"
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

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'

import { selectCustonOption } from '../helpers/certification.js'
import { useSignMethodsStore } from '../store/signMethods.js'

export default {
	name: 'ReadCertificate',
	components: {
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
			certificateData: [],
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
			this.certificateData = []
			this.error = ''
			this.size = 'small'
		},
		getLabelFromId(id) {
			try {
				const item = selectCustonOption(id).unwrap()
				return item.label
			} catch (error) {
				return id
			}
		},
		async send() {
			this.hasLoading = true
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/account/pfx/read'), {
				password: this.password,
			})
				.then(({ data }) => {
					this.certificateData = data.ocs.data
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

table {
	width: 100%;
	white-space: unset;
}

td {
	padding: 5px;
	border-bottom: 1px solid var(--color-border);
}

td:nth-child(2) {
	word-break: break-all;
}

th {
	font-weight: bold;
}

tr:last-child td {
	border-bottom: none;
}

tr :first-child {
	opacity: .5;
}
</style>
