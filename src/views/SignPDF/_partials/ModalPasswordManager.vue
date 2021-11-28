<template>
	<Modal size="normal" @close="onClose">
		<ModalContent v-if="isInput" class="modal-view-input">
			<template slot="header">
				<h2>{{ t('libresign', 'Subscription password.') }}</h2>
				<!-- <p>{{ t('libresign', 'Sign the document.') }}</p> -->
			</template>

			<div>
				<input v-model="value" type="password">
				<button :disabled="value.length < 3" @click="onChange(value)">
					{{ t('libresign', 'Sign the document.') }}
				</button>
			</div>
		</ModalContent>
		<ResetPassword v-if="isReset" class="modal-dialog" @close="onClose" />
		<CreatePassword v-if="isCreate"
			@changePfx="onChange"
			@close="onClose" />
	</Modal>
</template>

<script>
import ModalContent from '../../../Components/Modals/ModalContent.vue'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import ResetPassword from '../../../views/ResetPassword.vue'
import CreatePassword from '../../../views/CreatePassword.vue'

const VIEWS = Object.freeze({
	INPUT_PASSWORD: 0,
	CREATE_PASSWORD: 1,
	RESET_PASSWORD: 2,
})

export default {
	name: 'ModalPasswordManager',
	VIEWS,
	components: {
		Modal,
		ModalContent,
		ResetPassword,
		CreatePassword,
	},
	props: {
		hasPassword: {
			type: Boolean,
			required: true,
		},
	},
	data: () => ({
		view: VIEWS.INPUT_PASSWORD,
		value: '',
	}),
	computed: {
		isInput() {
			return this.view === VIEWS.INPUT_PASSWORD
		},
		isReset() {
			return this.view === VIEWS.RESET_PASSWORD
		},
		isCreate() {
			return this.view === VIEWS.CREATE_PASSWORD
		},
	},
	mounted() {
		if (!this.hasPassword) {
			this.view = VIEWS.CREATE_PASSWORD
		}
	},
	methods: {
		onClose() {
			this.$emit('close')
		},
		onChange(val) {
			this.$emit('change', val)
			this.$nextTick(() => {
				this.onClose()
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.modal-view-input {
	input {
		font-size: 1.6em;
		width: 100%;
		height: auto !important;
		display: block;
	}
	button {
		display: block;
		margin: 1em auto 0 auto;
	}
}
</style>
