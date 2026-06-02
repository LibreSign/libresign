<template>
  <div
    class="dropzone"
    :class="{ 'drag-active': isDragging }"
    @dragover.prevent="onDragOver"
    @dragleave="onDragLeave"
    @drop.prevent="onDrop"
  >
    <!-- ICON -->
    <div class="icon">
      <NcIconSvgWrapper :path="mdiCloudUpload" :size="32" />
    </div>

    <!-- TEXT -->
    <h3>{{ title }}</h3>
    <p>{{ description }}</p>

    <!-- ACTIONS SLOT -->
    <slot />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiCloudUpload } from '@mdi/js'

const emit = defineEmits<{
  (e: 'fileDrop', files: File[]): void
}>()

const props = withDefaults(defineProps<{
  multipleFiles: boolean
  title?: string
  description?: string
}>(), {
  multipleFiles: false,
  title: 'Drop your document here',
  description: 'PDF • Max 25MB'
})

const isDragging = ref(false)

function onDragOver() {
  isDragging.value = true
}

function onDragLeave(e: DragEvent) {
  if (
    e.currentTarget instanceof HTMLElement &&
    e.relatedTarget instanceof Node &&
    !e.currentTarget.contains(e.relatedTarget)
  ) {
    isDragging.value = false
  }
}

function onDrop(e: DragEvent) {
  isDragging.value = false

  let files = Array.from(e.dataTransfer?.files || [])
  if (!files.length) return

  // enforce single file if needed
  if (!props.multipleFiles) {
    files = [files[0]]
  }

  emit('fileDrop', files)
}
</script>

<style scoped>
.dropzone {
  border: 2px dashed #9adbb0;
  border-radius: 12px;
  padding: 32px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;

  background: linear-gradient(
    180deg,
    rgba(4, 213, 109, 0.08) 0%,
    rgba(4, 213, 109, 0.02) 100%
  );

  transition: all 0.2s ease;
}

.dropzone:hover {
	border-color: #04d56d;
	background: rgba(4, 213, 109, 0.1);
}

.dropzone.drag-active {
	border-color: #04d56d;
	background: rgba(4, 213, 109, 0.15);
	transform: scale(1.02);
	box-shadow: 0 0 0 3px rgba(4, 213, 109, 0.2);
}

.icon {
	margin-bottom: 12px;
	background: rgba(4, 213, 109, 0.1);
	color: var(--color-primary-element);
	border-radius: 50%;
	padding: 10px;
	width: 48px;
	height: 48px;
	display: flex;
	align-items: center;
	justify-content: center;
	margin: 0 auto 12px;
}

h3 {
	margin: 8px 0;
}

p {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}
</style>
