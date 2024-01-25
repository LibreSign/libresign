<template>
	<NcAppSidebar v-if="file"
		ref="sidebar"
		:class="{'app-sidebar--without-background lb-ls-root' : 'lb-ls-root'}"
		:name="file.name"
		:subtitle="subTitle"
		:active="tabId"
		:header="false"
		@update:active="updateActive"
		@close="closesidebar">
		<NcAppSidebarTab id="signantures"
			:name="t('libresign', 'Signatures')"
			icon="icon-rename"
			:order="1">
			<RightSidebar :items="file.signers" @update="update" @change-sign-tab="changeTab" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import { mapGetters } from 'vuex'
import { generateUrl } from '@nextcloud/router'
import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import NcAppSidebarTab from '@nextcloud/vue/dist/Components/NcAppSidebarTab.js'
import RightSidebar from './RightSidebar.vue'
import Moment from '@nextcloud/moment'

export default {
	name: 'Sidebar',
	components: {
		NcAppSidebar,
		NcAppSidebarTab,
		RightSidebar,
	},
	props: {
		loading: {
			type: Boolean,
			default: false,
			required: false,
		},
		viewsInFiles: {
			type: Boolean,
			default: false,
			required: false,
		},
	},
	data() {
		return {
			tabId: 'signatures',
		}
	},
	computed: {
		...mapGetters({
			file: 'files/getFile',
		}),
		subTitle() {
			return t('libresign', 'Requested by {name}, at {date}', {
				name: this.file.requested_by.uid
					? this.file.requested_by.uid
					: '',
				date: Moment(this.file.request_date, 'YYYY-MM-DD').toDate(),
			})
		},
		hasSign() {
			return this.file.signers.filter(
				signer => signer.me !== false && signer.sign_date === null,
			).length > 0
		},
		viewOnFiles() {
			return generateUrl('/f/' + this.file.file.nodeId)
		},
	},
	beforeDestroy() {
		this.$store.dispatch('sidebar/RESET')
	},

	methods: {
		closesidebar() {
			this.$emit('closesidebar', true)
		},
		validateFile() {
			this.$router.push({ name: 'validationFile', params: { uuid: this.file.uuid } })
		},
		update() {
			this.$emit('update', true)
		},
		updateActive(e) {
			this.changeTab(e)
		},
		changeTab(changeId) {
			this.tabId = changeId
		},
	},
}
</script>

<style lang="scss" scoped>
.actions{
	width: 100%;
	display: flex;
	margin-left: 10px;
}
</style>
