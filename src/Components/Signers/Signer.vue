<template>
	<div>
		<NcListItem :title="signer.displayName"
			:force-display-actions="true"
			@click="editItem">
			<template #icon>
				<NcAvatar :size="44" :display-name="signer.displayName" />
			</template>
			<template #subtitle>
				<Bullet v-for="method in identifyMethodsNames" :key="method" :name="method" />
			</template>
			<slot v-if="$slots.actions" slot="actions" name="actions" />
			<template #indicator>
				<CheckboxBlankCircle :size="16" :fill-color="statusColor" />
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
			if (this.signer.fileUserId) {
				return '#d67335'
			}
			// Draft, not saved
			return '#dbdbdb'
		},
	},
	methods: {
		editItem(signer) {
			if (!this.canRequestSign) {
				return
			}
			emit('libresign:edit-signer', this.signer)
		},
	},
}
</script>
