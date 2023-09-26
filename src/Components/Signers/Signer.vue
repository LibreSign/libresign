<template>
	<div>
		<NcListItem
			:title="name"
			:forceDisplayActions="true">
			<template #icon>
				<NcAvatar :size="44" display-name="displayName" />
			</template>
			<template #subtitle>
				<Bullet v-for="method in identifyMethodsNames" :key="method" :name="method" />
			</template>
			<template #actions>
				<NcActionButton
					ariaLabel="Delete"
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
		displayName: {
			type: String,
			required: true,
		},
		fileUserId: {
			type: Number,
			required: true,

		},
		identifyMethods: {
			type: Array,
			required: true,
		},
	},
	computed: {
		identifyMethodsNames() {
			return this.identifyMethods.map(method => Object.keys(method)[0])
		}
	},
	methods: {
		deleteItem() {
			
		}
	},
}
</script>
