<template>
	<div>
		<h3 v-if="subTitle">
			{{ subTitle }}
		</h3>
		<RequestSignature :file="file"
			:signers="signers"
			:name="subTitle" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import RequestSignature from '../Request/RequestSignature.vue'
import Moment from '@nextcloud/moment'

export default {
	name: 'AppFilesTab',
	components: {
		RequestSignature,
	},
	data() {
		return {
			file: {},
			signers: [],
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
				name: fileInfo.name
			}
			this.requestedBy = {}
			this.requestDate = ''
			try {
				const response = await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/file_id/${fileInfo.id}`))
				this.signers = response.data.signers
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
