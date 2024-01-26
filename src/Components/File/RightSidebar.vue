<template>
	<NcAppSidebar v-show="opened"
		:name="filesStore.file.name"
		:subtitle="subTitle"
		:active="filesStore.file.name"
		@close="closeSidebar">
		<RequestSignatureSidebar v-if="filesStore.file.name"
			:file="filesStore.file.file"
			:signers="filesStore.file.signers"
			:name="filesStore.file.name" />
	</NcAppSidebar>
</template>

<script>
import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import RequestSignatureSidebar from '../Request/RequestSignatureSidebar.vue'
import Moment from '@nextcloud/moment'
import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'

export default {
	name: 'RightSidebar',
	components: {
		NcAppSidebar,
		RequestSignatureSidebar,
	},
	setup() {
		const filesStore = useFilesStore()
		const sidebarStore = useSidebarStore()
		return { filesStore, sidebarStore }
	},
	computed: {
		subTitle() {
			if (this.filesStore.file.requested_by?.uid) {
				return t('libresign', 'Requested by {name}, at {date}', {
					name: this.filesStore.file.requested_by.uid,
					date: Moment(Date.parse(this.filesStore.file.request_date)).format('LL LTS'),
				})
			}
			return t('libresign', 'Enter who will receive the request')
		},
		opened() {
			return Object.keys(this.filesStore.file).length > 0
		},
	},
	methods: {
		closeSidebar() {
			this.$emit('close')
		},
	},
}
</script>
