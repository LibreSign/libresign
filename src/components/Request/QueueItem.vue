<template>
  <div
    class="queue-item"
    :class="item.status"
  >
    <!-- LEFT -->
    <div class="file-left">
      <div class="file-icon">
        <NcIconSvgWrapper :path="icon" :size="20" />
      </div>

      <div class="file-meta">
        <span class="file-name">
          {{ getItemName(item) }}
        </span>

        <!-- PROGRESS -->
        <div v-if="item.status === 'uploading'" class="progress-bar">
          <div
            class="progress-fill"
            :style="{ width: (item.progress || 0) + '%' }"
          />
        </div>

        <span class="file-sub">
          {{ subtitle }}
        </span>
      </div>
    </div>

    <!-- ACTIONS -->
    <div class="file-actions">
      <NcButton
        v-if="!isMobile"
        variant="tertiary"
        class="remove-btn"
        @click="emitRemove"
      >
        Remove
      </NcButton>

      <NcActions v-else>
        <NcActionButton @click="emitRemove">
          Remove
        </NcActionButton>
      </NcActions>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'

import { mdiFilePdfBox, mdiFolder } from '@mdi/js'

import { getItemName, type QueuedUploadItem } from '@/store/upload'

const props = defineProps<{
  item: QueuedUploadItem
}>()

const emit = defineEmits<{
  (e: 'remove'): void
}>()

/* RESPONSIVE */
const isMobile = ref(false)

function updateDevice() {
  isMobile.value = window.innerWidth <= 768
}

onMounted(() => {
  updateDevice()
  window.addEventListener('resize', updateDevice)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', updateDevice)
})

/* STATE */
const icon = computed(() =>
  props.item.type === 'file' ? mdiFilePdfBox : mdiFolder
)

const subtitle = computed(() => {
  if (props.item.type === 'file') {
    const kb = props.item.file.size / 1024
    return `PDF • ${kb.toFixed(1)} KB`
  }
  return 'From files'
})

function emitRemove() {
  emit('remove')
}
</script>

<style scoped>
.queue-item {
  display: flex;
  justify-content: space-between;
  align-items: center;

  padding: 14px 16px;
  border-radius: 12px;
  border: 1px solid var(--color-border);

  background: var(--color-main-background);

  transition: all 0.2s ease;
}

.queue-item.success {
  border-color: #04d56d;
  background: rgba(4, 213, 109, 0.08);
}

.queue-item.error {
  border-color: #ff4d4f;
  background: rgba(255, 77, 79, 0.08);
}

/* LEFT */
.file-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

/* ICON */
.file-icon {
  width: 36px;
  height: 36px;

  display: flex;
  align-items: center;
  justify-content: center;

  border-radius: 8px;

  background: rgba(4, 213, 109, 0.1);
  color: var(--color-primary-element);
}

/* TEXT */
.file-meta {
  display: flex;
  flex-direction: column;
}

.file-name {
  font-weight: 500;
  font-size: 14px;
  word-break: break-word;
}

.file-sub {
  font-size: 12px;
  color: var(--color-text-maxcontrast);
}

.progress-bar {
  width: 100%;
  height: 4px;
  background: rgba(0,0,0,0.05);
  border-radius: 4px;
  margin-top: 6px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #04d56d;
  transition: width 0.2s ease;
}

/* ACTIONS */
.file-actions {
  display: flex;
  align-items: center;
}

/* DESKTOP REMOVE */
.remove-btn {
  opacity: 0;
  transition: opacity 0.15s ease;
}

/* HOVER */
.queue-item:hover {
  background: rgba(4, 213, 109, 0.06);
  border-color: rgba(4, 213, 109, 0.3);
  transform: translateY(-1px);

  .remove-btn {
    opacity: 1;
  }
}

/* MOBILE */
@media (max-width: 768px) {
  .queue-item {
    padding: 12px;
  }

  .file-name {
    font-size: 13px;
  }

  .file-sub {
    font-size: 11px;
  }

  /* always show actions on mobile */
  .remove-btn {
    opacity: 1;
  }
}
</style>
