<script>
import { imagePath } from '@nextcloud/router'
import Signature from './Signature.vue'
import { service } from '../../../domains/signatures'

const emptySignature = {
	id: 0,
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
		async update({ base64, type }) {
			this.sings[type].value = base64

			this.$nextTick(() => {
				const entry = {
					...this.sings[type],
				}

				if (entry.id > 0) {
					// update
					service.updateSignature(entry.id, { type, base64 })
					return
				}

				// create
				service.createSignature(type, base64)
			})
		},
		async loadSignatures() {
			const { elements } = await service.loadSignatures()

			this.sings = (elements || [])
				.reduce((acc, current) => {
					acc[current.type] = {
						...emptySignature,
						id: current.id,
						value: imagePath('files', current.file.url),
					}
					return acc
				}, { ...this.sings })
		},
	},
}
</script>

<template>
	<div class="signatures">
		<h1>{{ t('libresign', 'Your signatures') }}</h1>

		<Signature :value="sings.signature.value" type="signature" v-on="{ update }">
			<template slot="title">
				{{ t('libresign', 'Signature') }}
			</template>

			<span slot="no-signatures">
				{{ t('libresign', 'No signature, click here to create a new') }}
			</span>
		</Signature>

		<Signature :value="sings.initial.value" type="initial" v-on="{ update }">
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

	h1{
		font-size: 1.3rem;
		font-weight: bold;
		border-bottom: 1px solid #000;
		padding-left: 5px;
		width: 100%;
	}
}
</style>
