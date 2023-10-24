<template>
	<div>
		<h2>{{ name }}</h2>
		<h3 v-if="subTitle">
			{{ subTitle }}
		</h3>
		<RequestSignature :file="file"
			:signers="signers"
			:name="name" />
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
			name: '',
			requestedBy: {},
		}
	},
	computed: {
		subTitle() {
			if ((this.file?.requested_by?.uid ?? '').length === 0) {
				return ''
			}
			return t('libresign', 'Requested by {name}, at {date}', {
				name: this.file.requested_by.uid,
				date: Moment(Date.parse(this.file.request_date)).format('LL LTS'),
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
			try {
				const response = await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/file_id/${fileInfo.id}`))
				this.signers = response.data.signers
				this.name = response.data.name
				this.file.uuid = response.data.uuid
				this.requestedBy = response.data.requested_by
			} catch (e) {
			}
		},
	},
}
</script>
<style lang="scss" scoped>
</style>
