<template>
	<div>
		<NcListItem :name="signer.displayName"
			:force-display-actions="true"
			@click="signerClickAction">
			<template #icon>
				<NcAvatar :size="44" :display-name="signer.displayName" />
			</template>
			<template #subtitle>
				<Bullet v-for="method in identifyMethodsNames" :key="method" :name="method" />
			</template>
			<slot v-if="$slots.actions" slot="actions" name="actions" />
			<template #indicator>
				<CheckboxBlankCircle :size="16"
					:fill-color="statusColor"
					:title="statusText" />
			</template>
		</NcListItem>
	</div>
</template>
<script>
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import CheckboxBlankCircle from 'vue-material-design-icons/CheckboxBlankCircle.vue'
import Bullet from '../Bullet/Bullet.vue'
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import Moment from '@nextcloud/moment'

export default {
	name: 'Signer',
	components: {
		NcListItem,
		NcAvatar,
		CheckboxBlankCircle,
		Bullet,
	},
	props: {
		signer: {
			type: Object,
			required: true,
		},
		event: {
			type: String,
			required: false,
			default: '',
		},
	},
	data() {
		return {
			canRequestSign: loadState('libresign', 'can_request_sign'),
		}
	},
	computed: {
		identifyMethodsNames() {
			return this.signer.identifyMethods.map(method => method.method)
		},
		statusColor() {
			if (this.signer.sign_date) {
				return '#008000'
			}
			// Pending
			if (this.signer.signRequestId) {
				return '#d67335'
			}
			// Draft, not saved
			return '#dbdbdb'
		},
		statusText() {
			if (this.signer.sign_date) {
				return t('libresign', 'signed at {date}', {
					date: Moment(this.signer.request_sign_date).format('LLL'),
				})
			}
			// Pending
			if (this.signer.signRequestId) {
				return t('libresign', 'pending')
			}
			// Draft, not saved
			return t('libresign', 'draft')
		},
	},
	methods: {
		signerClickAction(signer) {
			if (!this.canRequestSign) {
				return
			}
			if (this.event.length === 0) {
				return
			}
			if (this.signer.sign_date) {
				return
			}
			emit(this.event, this.signer)
		},
	},
}
</script>
