<template>
	<NcAppSidebar :title="titleName"
		:subtitle="subTitle"
		:empty="!isLibreSignFile">
		<RequestSignature :signers="file?.signers" />
	</NcAppSidebar>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import RequestSignature from '../Request/RequestSignature.vue'
import Moment from '@nextcloud/moment'

export default {
	name: 'SignaturesTab',
	components: {
		NcAppSidebar,
		RequestSignature,
	},
	props: {
		file: {
			type: Object,
			default: () => {},
			required: false,
		},
	},
	data() {
		return {
		}
	},
	computed: {
		titleName() {
			return this.file?.name ?? ''
		},
		subTitle() {
			return t('libresign', 'Requested by {name}, at {date}', {
				name: this.file?.requested_by?.uid ?? '',
				date: Moment(Date.parse(this.file?.request_date)).format('LL LTS'),
			})
		},
		isLibreSignFile() {
			return Object.keys(this.file ?? {}).length !== 0
		},
	},
	methods: {
		/**
		 * Load LibreSign data from Nextcloud File info
		 * @param fileInfo
		 */
		async update(fileInfo) {
			try {
				const response = await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/file_id/${fileInfo.id}`))
				console.log(response)
			} catch (e) {
				console.log(e, 'DEU ERROOOO')
			}
		},
	},
}
</script>
<style lang="scss" scoped>
#tab-libresign {
	.app-sidebar__close {
		display: none !important;
	}
}
</style>
