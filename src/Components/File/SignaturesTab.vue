<template>
	<NcAppSidebar :title="file.name"
		class="teste"
		:subtitle="subTitle"
		:empty="!isLibreSignFile">
		<RequestSignature :file="getFile"
			:signers="getSigners"
			:name="getName" />
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
	},
	data() {
		return {
			file: {},
			signers: [],
			name: '',
		}
	},
	computed: {
		getFile() {
			if (this.propFile && Object.keys(this.propFile).length > 0) {
				return this.propFile
			}
			return this.file
		},
		getSigners() {
			if (this.propSigners.length > 0) {
				return this.propSigners
			}
			return this.signers
		},
		getName() {
			if (this.propName.length > 0) {
				return this.propName
			}
			return this.name
		},
		subTitle() {
			if ((this.file.requested_by?.uid ?? '').length === 0) {
				return ''
			}
			return t('libresign', 'Requested by {name}, at {date}', {
				name: this.file.requested_by.uid,
				date: Moment(Date.parse(this.file.request_date)).format('LL LTS'),
			})
		},
		isLibreSignFile() {
			return Object.keys(this.file ?? {}).length !== 0
		},
	},
	methods: {
		/**
		 * Load LibreSign data from Nextcloud File info
		 * @param {object} fileInfo file data
		 */
		async update(fileInfo) {
			try {
				const response = await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/file_id/${fileInfo.id}`))
				this.signers = response.data.signers
				this.file.nodeId = fileInfo.id
				this.name = response.data.name
				this.file.uuid = response.data.uuid
			} catch (e) {
				this.signers = []
				this.file.nodeId = fileInfo.id
				this.name = fileInfo.name
			}
		},
	},
}
</script>
<style lang="scss" scoped>
.app-sidebar {
	:deep {
		.app-sidebar-header {
			display: none !important;
		}
	}
}
</style>
