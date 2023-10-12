<template>
	<NcAppSidebar :title="titleName"
		:subtitle="subTitle"
		:empty="!isLibreSignFile">
		<RequestSignature :signers="getSigners"
			@signer:update="signerUpdate" />
	</NcAppSidebar>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import RequestSignature from '../Request/RequestSignature.vue'
import { subscribe } from '@nextcloud/event-bus'
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
			signers: [],
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
		getSigners() {
			return (this.signers ?? []).concat(this.file?.signers ?? [])
		},
	},
	watch: {
		file() {
			this.signers = []
		},
	},
	async mounted() {
		subscribe('libresign:delete-signer', this.deleteSigner)
	},
	methods: {
		/**
		 * Load LibreSign data from Nextcloud File info
		 * @param fileInfo
		 */
		async update(fileInfo) {
			try {
				const response = await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/file_id/${fileInfo.id}`))
				this.signers = response.data.signers
			} catch (e) {
				this.signers = []
			}
		},
		deleteSigner(signer) {
			if (signer.identify) {
				this.signers = this.signers.filter((i) => i.identify !== signer.identify)
			}
		},
		signerUpdate(signer) {
			if (!signer) {
				return
			}
			// Remove before if already exists
			for (let i = this.signers.length - 1; i >= 0; --i) {
				if (this.signers[i].identify?.length > 0 && signer.identify?.length > 0 && this.signers[i].identify === signer.identify) {
					this.signers.splice(i, 1)
				}
			}
			this.signers.push(signer)
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
