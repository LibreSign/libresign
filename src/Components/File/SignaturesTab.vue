<template>
	<NcAppSidebar :title="titleName"
		:subtitle="subTitle"
		:empty="!isLibreSignFile">
		<RequestSignature :signers="getSigners"
			:file="getFile"
			@signer:save="signerSave"
			@signer:update="signerUpdate" />
	</NcAppSidebar>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import RequestSignature from '../Request/RequestSignature.vue'
import { emit, subscribe } from '@nextcloud/event-bus'
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
			fileInfo: {},
		}
	},
	computed: {
		titleName() {
			return this.file?.name ?? this.fileInfo?.name ?? ''
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
		getFile() {
			return {
				uuid: this.file?.uuid,
			}
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
			this.fileInfo = fileInfo
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
			// Ignore if already exists
			for (let i = this.signers.length - 1; i >= 0; --i) {
				if (this.signers[i].identify?.length > 0 && signer.identify?.length > 0 && this.signers[i].identify === signer.identify) {
					return
				}
			}
			// Ignore if already exists
			if (this.file?.signers) {
				for (let i = this.file.signers.length - 1; i >= 0; --i) {
					if (this.file.signers[i].fileUserId === signer.identify) {
						return
					}
				}
			}
			this.signers.push(signer)
		},
		async signerSave() {
			const params = {
				name: this.titleName,
				users: [],
			}
			this.getSigners.forEach(signer => {
				const user = {
					displayName: signer.displayName,
					identify: {},
				}
				signer.identifyMethods.forEach(method => {
					if (method.method === 'account') {
						user.identify.account = method?.value?.id ?? signer.uid
					} else if (method.method === 'email') {
						user.identify.email = method.value ?? signer.email
					}
				})
				params.users.push(user)
			})

			if (this.file?.uuid) {
				params.uuid = this.file.uuid
				try {
					await axios.patch(generateOcsUrl('/apps/libresign/api/v1/request-signature'), params)
				} catch (e) {
				}
				return
			}
			params.file = {
				fileId: this.fileInfo.id,
			}
			try {
				await axios.post(generateOcsUrl('/apps/libresign/api/v1/request-signature'), params)
			} catch (e) {
			}
			emit('libresign:show-visible-elements')
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
