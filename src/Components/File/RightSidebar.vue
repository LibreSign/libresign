<template>
	<NcAppSidebar :name="propName"
		:subtitle="subTitle"
		:active="propName"
		@close="closeSidebar">
		<RequestSignature :file="propFile"
			:signers="propSigners"
			:name="propName" />
	</NcAppSidebar>
</template>

<script>
import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import RequestSignature from '../Request/RequestSignature.vue'
import Moment from '@nextcloud/moment'

export default {
	name: 'RightSidebar',
	components: {
		NcAppSidebar,
		RequestSignature,
	},
	props: {
		propName: {
			type: String,
			default: '',
			required: false,
		},
		propFile: {
			type: Object,
			default: () => {},
			required: false,
		},
		propSigners: {
			type: Array,
			default: () => [],
			required: false,
		},
		propRequestedBy: {
			type: Object,
			default: () => {},
			required: false,
		},
		propRequestDate: {
			type: String,
			default: '',
			required: false,
		},
	},
	computed: {
		subTitle() {
			if (this.propRequestedBy?.uid) {
				return t('libresign', 'Requested by {name}, at {date}', {
					name: this.propRequestedBy.uid,
					date: Moment(Date.parse(this.propRequestDate)).format('LL LTS'),
				})
			}
			return t('libresign', 'Enter who will receive the request')
		},
	},
	methods: {
		closeSidebar() {
			this.$emit('close')
		},
	},
}
</script>
<style lang="scss" scoped>
</style>
