<template>
	<AppSidebar
		v-if="file"
		ref="sidebar"
		:class="{'app-sidebar--without-background lb-ls-root' : 'lb-ls-root'}"
		:title="titleName"
		:subtitle="subTitle"
		:active="tabId"
		:header="false"
		name="sidebar"
		@update:active="updateActive"
		@close="closeSidebar">
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
			<SignaturesTab :items="file.signers" @update="update" @change-sign-tab="changeTab" />
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
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import { mapGetters } from 'vuex'
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
			file: 'myFiles/getFile',
			statusSidebar: 'sidebar/getStatus',
		}),
		titleName() {
			return this.file.name ? this.file.name : ''
		},
		subTitle() {
			return t('libresign', 'Requested by {name}, at {date}', {
				name: this.file.requested_by.uid
					? this.file.requested_by.uid
					: '',
				date: format(new Date(this.file.request_date), 'dd/MM/yyyy'),
			})
		},
		hasSign() {
			return this.file.signers.filter(
				signer => signer.me !== false && signer.sign_date === null
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
		closeSidebar() {
			this.$emit('closeSidebar', true)
		},
		validateFile() {
			this.$router.push({ name: 'validationFile', params: { uuid: this.file.uuid } })
		},
		update() {
			this.$emit('update', true)
		},
		clearSignInput() {
			this.$refs.sign.clearInput()
		},
		emitSign(password) {
			this.$emit('sign:document', { password, fileId: this.file.file.nodeId })
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
