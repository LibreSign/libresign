<template>
	<div>
		<h3 v-if="subTitle">
			{{ subTitle }}
		</h3>
		<RequestSignatureSidebar :file="file"
			:signers="signers"
			:name="name" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import RequestSignatureSidebar from '../Request/RequestSignatureSidebar.vue'
import Moment from '@nextcloud/moment'

export default {
	name: 'AppFilesTab',
	components: {
		RequestSignatureSidebar,
	},
	data() {
		return {
			file: {},
			signers: [],
			name: '',
			requestedBy: {},
			requestDate: '',
		}
	},
	computed: {
		subTitle() {
			if ((this.requestedBy?.uid ?? '').length === 0 || this.requestDate.length === 0) {
				return ''
			}
			return t('libresign', 'Requested by {name}, at {date}', {
				name: this.requestedBy.uid,
				date: Moment(Date.parse(this.requestDate)).format('LL LTS'),
			})
		},
	},
	methods: {
		/**
		 * Load LibreSign data from Nextcloud File info
		 * @param {object} fileInfo file data
		 */
		async update(fileInfo) {
			this.signers = []
			this.file = {
				nodeId: fileInfo.id,
			}
			this.name = fileInfo.name
			this.requestedBy = {}
			this.requestDate = ''
			try {
				const response = await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/file_id/${fileInfo.id}`))
				this.signers = response.data.signers
				this.name = response.data.name
				this.file.uuid = response.data.uuid
				this.requestedBy = response.data.requested_by
				this.requestDate = response.data.request_date
			} catch (e) {
			}
		},
	},
}
</script>
<style lang="scss" scoped>
</style>
