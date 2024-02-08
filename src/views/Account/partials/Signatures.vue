<template>
	<div class="signatures">
		<h1>{{ t('libresign', 'Your signatures') }}</h1>

		<Signature :id="signs.signature.id"
			type="signature"
			:value="signs.signature.value"
			@signature:delete="signatureDelete"
			v-on="{ save }">
			<template slot="title">
				{{ t('libresign', 'Signature') }}
			</template>

			<span slot="no-signatures">
				{{ t('libresign', 'No signature, click here to create a new') }}
			</span>
		</Signature>

		<Signature v-if="false"
			:id="signs.initial.id"
			type="initial"
			:value="signs.initial.value"
			v-on="{ save }">
			<template slot="title">
				{{ t('libresign', 'Initials') }}
			</template>

			<span slot="no-signatures">
				{{ t('libresign', 'No initials, click here to create a new') }}
			</span>
		</Signature>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import Signature from './Signature.vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export default {
	name: 'Signatures',
	components: {
		Signature,
	},
	data() {
		return {
			signs: {
				signature: {
					id: 0,
					fileId: 0,
					value: '',
				},
				initial: {
					id: 0,
					fileId: 0,
					value: '',
				},
			},
		}
	},
	mounted() {
		this.loadSignatures()
	},
	methods: {
		async save({ base64, type }) {
			this.signs[type].value = base64
			this.loadSignatures()
		},
		signatureDelete({ type }) {
			this.signs[type] = {
				id: 0,
				fileId: 0,
				value: '',
			}
			this.loadSignatures()
		},
		async loadSignatures() {
			try {
				const response = await axios.get(generateOcsUrl('/apps/libresign/api/v1/account/signature/elements'))

				response.data.elements.forEach(current => {
					this.signs[current.type] = current
				})
			} catch (err) {
				showError(err.response.data.message)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.signatures {
	align-items: flex-start;
	width: 100%;

	h1{
		font-size: 1.3rem;
		font-weight: bold;
		border-bottom: 1px solid #000;
		padding-left: 5px;
		width: 100%;
	}
}
</style>
