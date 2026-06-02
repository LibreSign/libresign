<template>
  <div
    v-if="file"
    class="file-details-card"
    :class="[statusClass, { ready: isReady }]"
  >
    <!-- HEADER -->
    <div class="file-details-header">
      <div class="file-icon-wrapper">
        <NcIconSvgWrapper :path="icon" :size="20" />
      </div>

      <div class="file-title">
        <strong>
          {{ file.name }}
          <span v-if="extension">
            .{{ extension }}
          </span>
        </strong>

        <!-- STATUS -->
        <div class="file-status-row">
          <span class="status-dot" />
          <span class="status-text">
            {{ statusLabel }}
          </span>

          <span class="file-type">
            · {{ isEnvelope ? 'Envelope' : 'File' }}
          </span>
        </div>
      </div>
    </div>

    <!-- META -->
    <div class="file-meta">
      <div class="meta-row">
        <span>Pages</span>
        <span>{{ pages }}</span>
      </div>

      <div class="meta-row">
        <span>Created</span>
        <span>{{ formattedDate }}</span>
      </div>

      <div class="meta-row">
        <span>Signers</span>
        <span>{{ signersCount }}</span>
      </div>
    </div>

    <!-- PROGRESS HINT -->
    <div class="file-progress-hint">
      {{ progressHint }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { mdiFile, mdiFolder } from '@mdi/js'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { useFilesStore } from '../../store/files.js'

defineOptions({
  name: 'FileDetailsCard',
})

const filesStore = useFilesStore()

/* ===================== */
/* BASE ITEM */
/* ===================== */
const file = computed(() => filesStore.getFile())

/* ===================== */
/* TYPE */
/* ===================== */
const isEnvelope = computed(() => file.value?.nodeType === 'envelope')

/* ===================== */
/* ICON */
/* ===================== */
const icon = computed(() => {
  return isEnvelope.value ? mdiFolder : mdiFile
})

/* ===================== */
/* EXTENSION */
/* ===================== */
const extension = computed(() => {
  if (isEnvelope.value) return null
  return file.value?.metadata?.extension || null
})

/* ===================== */
/* META */
/* ===================== */
const pages = computed(() => {
  // If it's an envelope, sum the pages of all files within it
  if (isEnvelope.value) {
    return file.value?.files?.reduce((total, f) => {
      // Use metadata.p first, then fall back to totalPages, then 0
      const filePages = f.metadata?.p ?? f.totalPages ?? 0;
      return total + filePages;
    }, 0) ?? 0;
  }

  // Fallback for a single file (not an envelope)
  return file.value?.metadata?.p ?? 0;
});

const signersCount = computed(() => {
  return file.value?.signersCount ?? file.value?.signers?.length ?? 0
})

/* ===================== */
/* STATUS */
/* ===================== */
const hasPositions = computed(() => {
  return (file.value?.visibleElements?.length || 0) > 0
})

const statusLabel = computed(() => {
  if (!signersCount.value) return 'Add signer'
  if (!hasPositions.value) return 'Set positions'
  return 'Ready'
})

const statusClass = computed(() => {
  if (!signersCount.value) return 'status-warning'
  if (!hasPositions.value) return 'status-warning'
  return 'status-success'
})

const isReady = computed(() => statusClass.value === 'status-success')

/* ===================== */
/* PROGRESS HINT */
/* ===================== */
const progressHint = computed(() => {
  if (!signersCount.value) return 'No signers added yet'
  if (!hasPositions.value) return 'Signature positions not set'
  return 'Ready to request signatures'
})

/* ===================== */
/* DATE (SAFE) */
/* ===================== */
function hasCreatedAt(f: any): f is { created_at: string } {
  return 'created_at' in f
}

const formattedDate = computed(() => {
  const f = file.value
  if (!f || !hasCreatedAt(f)) return 'Draft'
  return new Date(f.created_at).toLocaleDateString()
})
</script>

<style scoped>
.file-details-card {
  padding: 14px;
  margin-bottom: 14px;
  border-radius: 12px;
  background: var(--color-background-hover);
  transition: all 0.25s ease;
}

/* READY STATE */
.file-details-card.ready {
  border: 1px solid rgba(4, 213, 109, 0.35);
}

/* HEADER */
.file-details-header {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-bottom: 10px;
}

/* ICON */
.file-icon-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;

  background: rgba(4, 213, 109, 0.1);
  border-radius: 50%;
  color: var(--color-primary-element);

  padding: 7px;
  width: 36px;
  height: 36px;
}

/* TITLE */
.file-title {
  display: flex;
  flex-direction: column;
}

/* STATUS ROW */
.file-status-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--color-text-maxcontrast);
}

/* TYPE LABEL */
.file-type {
  opacity: 0.7;
}

/* STATUS DOT */
.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #f59e0b; /* default amber */
}

/* PULSE ONLY WHEN ACTION NEEDED */
.status-warning .status-dot {
  animation: pulse 1.6s infinite;
}

/* READY (GREEN) */
.status-success .status-dot {
  background: #04d56d;
  animation: none;
}

/* META */
.file-meta {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  margin-bottom: 8px;
}

.meta-row {
  display: flex;
  justify-content: space-between;
}

/* PROGRESS HINT */
.file-progress-hint {
  font-size: 12px;
  color: var(--color-text-maxcontrast);
  opacity: 0.85;
}

/* PULSE ANIMATION */
@keyframes pulse {
  0% {
    transform: scale(1);
    opacity: 1;
  }
  70% {
    transform: scale(1.6);
    opacity: 0.4;
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}
</style>
