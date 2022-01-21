<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { get } from 'lodash-es'
import Signature from './Signature.vue'
import { service } from '../../../domains/signatures'

const emptySignature = {
	id: 0,
	fileId: 0,
	value: '',
}

export default {
	name: 'Signatures',
	components: {
		Signature,
	},
	data() {
		return {
			sings: {
				signature: { ...emptySignature },
				initial: { ...emptySignature },
			},
		}
	},
	mounted() {
		this.loadSignatures()
	},
	methods: {
		onError(err) {
			const message = get(err, ['response', 'data', 'message'], err.message)

			showError(message)
		},
		save({ base64, type }) {
			this.sings[type] = {
				...this.sings[type],
				value: base64,
			}

			this.$nextTick(async() => {
				const entry = {
					...this.sings[type],
				}

				entry.id > 0
					? await this.update(entry.id, { type, base64 })
					: await this.create({ type, base64 })

				this.loadSignatures()
			})
		},
		async update(id, { type, base64 }) {
			try {
				const res = await service.updateSignature(id, { type, base64 })
				showSuccess(res.message)
			} catch (err) {
				this.onError(err)
			}
		},
		async create({ type, base64 }) {
			try {
				const res = await service.createSignature(type, base64)
				showSuccess(res.message)
			} catch (err) {
				this.onError(err)
			}

		},
		async loadSignatures() {
			try {
				const { elements } = await service.loadSignatures()

				this.sings = (elements || [])
					.reduce((acc, current) => {
						acc[current.type] = {
							...emptySignature,
							id: current.id,
							fileId: current.file.fileId,
							value: current.file.url,
						}
						return acc
					}, { ...this.sings })
			} catch (err) {
				this.onError(err)
			}
		},
	},
}
</script>

<template>
	<div class="signatures">
		<h1>{{ t('libresign', 'Your signatures') }}</h1>

		<Signature :value="sings.signature.value" type="signature" v-on="{ save }">
			<template slot="title">
				{{ t('libresign', 'Signature') }}
			</template>

			<span slot="no-signatures">
				{{ t('libresign', 'No signature, click here to create a new') }}
			</span>
		</Signature>

		<Signature :value="sings.initial.value" type="initial" v-on="{ save }">
			<template slot="title">
				{{ t('libresign', 'Initials') }}
			</template>

			<span slot="no-signatures">
				{{ t('libresign', 'No initials, click here to create a new') }}
			</span>
		</Signature>
	</div>
</template>

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
