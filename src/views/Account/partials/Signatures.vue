<script>
import Signature from './Signature.vue'

export default {
	name: 'Signatures',
	components: {
		Signature,
	},
	data() {
		return {
			sings: {
				signature: '',
				initials: '',
			},
		}
	},
	methods: {
		update({ base64, type }) {
			this.sings[type] = base64
		},
	},
}
</script>

<template>
	<div class="signatures">
		<h1>{{ t('libresign', 'Your signatures') }}</h1>

		<Signature :value="sings.signature" type="signature" v-on="{ update }">
			<template slot="title">
				{{ t('libresign', 'Signature') }}
			</template>

			<span slot="no-signatures">
				{{ t('libresign', 'No signature, click here to create a new') }}
			</span>
		</Signature>

		<Signature :value="sings.initials" type="initials" v-on="{ update }">
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
