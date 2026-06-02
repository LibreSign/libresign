<template>
  <TransitionGroup
    name="queue"
    tag="div"
    class="queue-list"
  >
    <QueueItem
      v-for="(item, index) in items"
      :key="getKey(item, index)"
      :item="item"
      :style="{ transitionDelay: `${index * 40}ms` }"
      @remove="() => emit('remove', index)"
    />
  </TransitionGroup>
</template>

<script setup lang="ts">
import QueueItem from './QueueItem.vue'
import type { QueuedUploadItem } from '@/store/upload'

defineProps<{
  items: QueuedUploadItem[]
}>()

const emit = defineEmits<{
  (e: 'remove', index: number): void
}>()

function getKey(item: QueuedUploadItem, index: number) {
  return item.type === 'file'
    ? `${item.file.name}-${item.file.lastModified}-${index}`
    : `${item.path}-${index}`
}
</script>

<style scoped>
.queue-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 12px;
}

.queue-enter-active,
.queue-leave-active {
  transition: all 0.25s ease;
}

.queue-enter-from {
  opacity: 0;
  transform: translateY(8px) scale(0.98);
}

.queue-leave-to {
  opacity: 0;
  transform: translateY(-8px) scale(0.98);
}

.queue-move {
  transition: transform 0.25s ease;
}
</style>
