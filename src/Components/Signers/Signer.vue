<template>
	<div>
		<NcListItem :title="signer.displayName"
			:force-display-actions="true"
			@click="editItem">
			<template #icon>
				<NcAvatar :size="44" display-name="signer.displayName" />
			</template>
			<template #subtitle>
				<Bullet v-for="method in identifyMethodsNames" :key="method" :name="method" />
			</template>
			<template #actions>
				<NcActionButton v-if="canRequestSign"
					aria-label="Delete"
					@click="deleteItem">
					<template #icon>
						<Delete :size="20" />
					</template>
				</NcActionButton>
			</template>
		</NcListItem>
	</div>
</template>
<script>
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import Delete from 'vue-material-design-icons/Delete.vue'
import Bullet from '../Bullet/Bullet.vue'
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'Signer',
	components: {
		NcListItem,
		NcAvatar,
		NcActionButton,
		Delete,
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
	},
	methods: {
		deleteItem() {
			emit('libresign:delete-signer', this.signer)
		},
		editItem(signer) {
			if (!this.canRequestSign) {
				return
			}
			emit('libresign:edit-signer', this.signer)
		},
	},
}
</script>
