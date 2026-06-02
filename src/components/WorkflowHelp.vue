<template>
	<div class="workflow-help">

		<p v-if="currentFileId === 0">
			Upload or select a document to begin the signature request process.
		</p>

		<p v-else-if="currentFile?.signersCount === 0">
			Add one or more signers to your document.
		</p>

		<p v-else>
			Click on the document to place signature fields.
		</p>

	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useFilesStore } from '../store/files'

defineOptions({
	name: 'WorkflowHelp',
})

const filesStore = useFilesStore()

const currentFileId = computed(() => filesStore.selectedFileId)

const currentFile = computed(() => {
	return filesStore.files?.[currentFileId.value]
})
</script>

<style lang="scss" scoped>
	.workflow-help {
		margin-top: 8px;
		max-width: 520px;
		font-size: 14px;
		color: var(--color-text-maxcontrast);
		text-align: center;
		background: var(--color-background-hover);
		padding: 10px 14px;
		border-radius: 8px;
	}
</style>
