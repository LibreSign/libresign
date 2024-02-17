<template>
	<div class="signatures">
		<h1>{{ t('libresign', 'Your signatures') }}</h1>

		<Signature type="signature">
			<template slot="title">
				{{ t('libresign', 'Signature') }}
			</template>

			<span slot="no-signatures">
				{{ t('libresign', 'No signature, click here to create a new') }}
			</span>
		</Signature>

		<Signature v-if="false" type="initial">
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
import Signature from './Signature.vue'
import { useSignatureElementsStore } from '../../../store/signatureElements.js'

export default {
	name: 'Signatures',
	components: {
		Signature,
	},
	setup() {
		const signatureElementsStore = useSignatureElementsStore()
		return { signatureElementsStore }
	},
	mounted() {
		this.signatureElementsStore.loadSignatures()
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
