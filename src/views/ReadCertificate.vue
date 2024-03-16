<template>
	<NcContent app-name="libresign" class="with-sidebar--full">
		<form @submit="e => e.preventDefault()">
			<header>
				<h2>{{ t('libresign', 'Certificate data') }}</h2>
			</header>
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
							{{ t('libresign', 'Extra informations') }}
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
					<NcPasswordField :disabled="hasLoading"
						:label="t('libresign', 'Certificate password')"
						:placeholder="t('libresign', 'Certificate password')"
						:value.sync="password" />
				</div>
				<NcButton :disabled="hasLoading" @click="send()">
					<template #icon>
						<NcLoadingIcon v-if="hasLoading" :size="20" />
					</template>
					{{ t('libresign', 'Confirm') }}
				</NcButton>
			</div>
		</form>
	</NcContent>
</template>

<script>
import '@nextcloud/password-confirmation/dist/style.css' // Required for dialog styles
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { selectCustonOption } from '../helpers/certification.js'

export default {
	name: 'ReadCertificate',
	components: {
		NcContent,
		NcPasswordField,
		NcButton,
		NcLoadingIcon,
	},
	data() {
		return {
			hasLoading: false,
			password: '',
			certificateData: [],
		}
	},
	methods: {
		getLabelFromId(id) {
			const item = selectCustonOption(id).unwrap()
			return item.label
		},
		async send() {
			this.hasLoading = true
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/account/pfx/read'), {
				password: this.password,
			})
				.then(({ data }) => {
					this.certificateData = data
				})
				.catch(({ response }) => {
					this.signMethodsStore.setHasSignatureFile(false)
					if (response.data.message) {
						showError(response.data.message)
					} else {
						showError(t('libresign', 'Error creating new password, please contact the administrator'))
					}
				})
			this.hasLoading = false
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
	display: block;
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
