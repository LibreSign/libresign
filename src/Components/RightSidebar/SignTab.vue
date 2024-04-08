<template>
	<div class="sign-pdf-sidebar">
		<header>
			<Chip>
				{{ signStore.document.statusText }}
			</Chip>
		</header>

		<main>
			<div v-if="loading" class="sidebar-loading">
				<p>
					{{ t('libresign', 'Loading …') }}
				</p>
			</div>
			<div v-if="!signEnabled">
				{{ t('libresign', 'Document not available for signature.') }}
			</div>
			<Sign v-else-if="!loading"
				@signed="onSigned" />
		</main>
	</div>
</template>

<script>
import { SIGN_STATUS } from '../../domains/sign/enum.js'
import Chip from '../../Components/Chip.vue'
import Sign from '../../views/SignPDF/_partials/Sign.vue'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { useSignStore } from '../../store/sign.js'

export default {
	name: 'SignTab',
	components: {
		Chip,
		Sign,
	},
	setup() {
		const signStore = useSignStore()
		return { signStore }
	},
	data() {
		return {
			loading: true,
		}
	},
	mounted() {
		subscribe('pdfeditor:loaded', this.loaded)
	},
	beforeUnmount() {
		unsubscribe('pdfeditor:loaded')
	},
	methods: {
		signEnabled() {
			return SIGN_STATUS.ABLE_TO_SIGN === this.signStore.document.status
				|| SIGN_STATUS.PARTIAL_SIGNED === this.signStore.document.status
		},
		loaded() {
			this.loading = false
		},
		onSigned(data) {
			this.$router.push({
				name: 'DefaultPageSuccess',
				params: {
					uuid: data.file.uuid,
				},
			})
		},
	},
}
</script>

<style lang="scss" scoped>
header {
	text-align: center;
	width: 100%;
	margin-top: 1em;
	margin-bottom: 3em;
}
main {
	flex-direction: column;
	align-items: center;
	width: 100%;
	.sidebar-loading {
		text-align: center;
	}
}
</style>
