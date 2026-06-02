<template>
	<div class="file-status-indicator">
		<span :class="['status-dot', statusClass]" />
		<span class="status-text">{{ statusLabel }}</span>
	</div>
</template>

<script>
import { useFilesStore } from '../../store/files.js'

export default {
	name: 'FileStatusIndicator',

	setup() {
		const filesStore = useFilesStore()

		return {
			filesStore,
		}
	},

	computed: {
		file() {
			return this.filesStore.getFile()
		},

		statusClass() {
			if (!this.file) return ''

			if (this.file.signersCount === 0) return 'draft'

			if (this.file.signersCount > 0 && !this.file.visibleElements?.length) {
				return 'setup'
			}

			return 'ready'
		},

		statusLabel() {
			if (!this.file) return ''

			if (this.file.signersCount === 0) return 'Draft'

			if (this.file.signersCount > 0 && !this.file.visibleElements?.length) {
				return 'Awaiting signature position setup'
			}

			return 'Ready to request signatures'
		},
	},
}
</script>

<style lang="scss" scoped>
.file-status-indicator {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 13px;
}

.status-dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
}

.status-dot.draft {
	background: #e74c3c;
}

.status-dot.setup {
	background: #f39c12;
}

.status-dot.ready {
	background: #2ecc71;
}

</style>
