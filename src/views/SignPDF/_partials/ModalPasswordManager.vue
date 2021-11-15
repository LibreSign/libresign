<template>
	<Modal size="normal"
		@close="onClose">
		<ResetPassword v-if="hasPassword" class="modal-dialog" @close="onClose" />
		<CreatePassword v-if="!hasPassword"
			@changePfx="onChange"
			@close="onClose" />
	</Modal>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import ResetPassword from '../../../views/ResetPassword.vue'
import CreatePassword from '../../../views/CreatePassword.vue'

export default {
	name: 'ModalPasswordManager',
	components: {
		Modal,
		ResetPassword,
		CreatePassword,
	},
	props: {
		hasPassword: {
			type: Boolean,
			required: true,
		},
	},
	methods: {
		onClose() {
			this.$emit('close')
		},
		onChange(val) {
			this.$emit('change', val)
		},
	},
}
</script>
