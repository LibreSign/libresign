<template>
	<NcAppSidebar :title="propName"
		:subtitle="subTitle">
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
	name: 'LibresignTab',
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
			return t('libresign', 'Requested by {name}, at {date}', {
				name: this.propRequestedBy.uid,
				date: Moment(Date.parse(this.propRequestDate)).format('LL LTS'),
			})
		},
	},
}
</script>
<style lang="scss" scoped>
</style>
