<template>
	<AppSidebar
		v-if="currentFile"
		ref="sidebar"
		:class="{'app-sidebar--without-background lb-ls-root' : 'lb-ls-root'}"
		:title="titleName"
		:subtitle="subTitle"
		:active="tabId"
		:header="false"
		name="sidebar"
		@update:active="updateActive"
		@close="closeSidebar">
		<template #secondary-actions>
			<ActionLink v-if="isOwner" icon="icon-folder" :href="viewOnFiles">
				{{ t('libresign', 'View on Files') }}
			</ActionLink>
		</template>
		<div class="actions">
			<button class="secondary" @click="validateFile">
				{{ t('libresign', 'Validate File') }}
			</button>
		</div>
		<AppSidebarTab
			id="signantures"
			:name="t('libresign', 'Signatures')"
			icon="icon-rename"
			:order="1">
			<SignaturesTab :items="currentFile.file.signers" @update="update" @change-sign-tab="changeTab" />
		</AppSidebarTab>
		<AppSidebarTab
			v-if="hasSign"
			id="sign"
			:name="t('libresign', 'Sign')"
			icon="icon-rename"
			:order="2">
			<Sign ref="sign"
				:pfx="getHasPfx"
				:has-loading="loading"
				@sign:document="emitSign" />
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { ActionLink } from '@nextcloud/vue'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import { mapGetters, mapState } from 'vuex'
import SignaturesTab from './SignaturesTab.vue'
import Sign from '../Sign'
import format from 'date-fns/format'

export default {
	name: 'Sidebar',
	components: {
		AppSidebar,
		AppSidebarTab,
		SignaturesTab,
		Sign,
		ActionLink,
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
		titleName() {
			return this.getCurrentFile.file.name ? this.getCurrentFile.file.name : ''
		},
		isOwner() {
			return this.getCurrentFile.file.signers.filter(signer => (signer.me === true))
		},
		subTitle() {
			return t('libresign', 'Requested by {name}, at {date}', {
				name: this.getCurrentFile.file.requested_by.uid
					? this.getCurrentFile.file.requested_by.uid
					: '',
				date: format(new Date(this.getCurrentFile.file.request_date), 'dd/MM/yyyy'),
			})
		},
		hasSign() {
			return this.getCurrentFile.file.signers.filter(
				signer => signer.me !== false && signer.sign_date === null
			).length > 0
		},
		viewOnFiles() {
			return generateUrl('/f/' + this.currentFile.file.file.nodeId)
		},
		...mapState({
			currentFile: state => state.currentFile,
			sidebar: state => state.sidebar,
		}),
		...mapGetters(['getCurrentFile', 'getSidebar', 'getHasPfx']),
	},
	methods: {
		closeSidebar() {
			this.$emit('closeSidebar', true)
		},
		validateFile() {
			this.$router.push({ name: 'validationFile', params: { uuid: this.getCurrentFile.file.uuid } })
		},
		update() {
			this.$emit('update', true)
		},
		clearSignInput() {
			this.$refs.sign.clearInput()
		},
		emitSign(password) {
			this.$emit('sign:document', { password, fileId: this.getCurrentFile.file.file.nodeId })
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
