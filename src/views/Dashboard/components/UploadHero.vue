<template>
  <div class="card upload-hero">

    <UploadDropzone :multiple-files="envelopeEnabled" @fileDrop="handleDrop">

      <!-- EMPTY STATE -->
      <template v-if="!hasFiles">
        <UploadActions
          :allow-multiple="envelopeEnabled"
          @upload="() => navigate('upload')"
          @uploadUrl="() => navigate('uploadUrl')"
          @pickFile="() => navigate('pickFile')"
        />
      </template>

      <!-- FILE STATE -->
      <template v-else>
        <div class="file-preview">

          <div
            v-for="(file, index) in files"
            :key="index"
            class="file-item"
          >
            <div class="file-info">
              <span class="file-name">{{ getItemName(file) }}</span>
              <span class="file-size" v-if="file.type === 'file'">
					{{ formatSize(file.file.size) }}
			  </span>
            </div>

            <button class="remove-btn" @click="removeFile(index)">
              Remove
            </button>
          </div>

          <!-- ADD MORE ACTIONS -->
          <UploadActions
            :allow-multiple="envelopeEnabled"
            @upload="() => navigate('upload')"
            @uploadUrl="() => navigate('uploadUrl')"
            @pickFile="() => navigate('pickFile')"
          />

          <!-- SUBMIT -->
          <button class="submit-btn" @click="goToRequest()">
            Continue to signing →
          </button>

        </div>
      </template>

    </UploadDropzone>

  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { getCapabilities } from '@nextcloud/capabilities'

import UploadDropzone from '@/components/Request/UploadDropzone.vue'
import UploadActions, { type UploadAction } from '@/components/Request/UploadActions.vue'

import {
  addPendingItems,
  getPendingItems,
  getItemName,
  type QueuedUploadItem,
} from '@/store/upload'

import type { LibresignCapabilities } from '@/types'

const router = useRouter()

/* ===================== */
/* CONFIG */
/* ===================== */
function getLibresignConfig() {
  const capabilities = getCapabilities() as LibresignCapabilities | undefined
  return capabilities?.libresign?.config ?? null
}

const config = getLibresignConfig()

const envelopeEnabled = computed(() => {
  return config?.envelope?.['is-available'] === true
})

/* ===================== */
/* FILE STORE */
/* ===================== */
const filesRef = getPendingItems()

const files = computed(() => filesRef.value)
const hasFiles = computed(() => files.value.length > 0)

/* ===================== */
/* ACTIONS */
/* ===================== */
function handleDrop(files: File[]) {
  addPendingItems(
    files.map(file => ({
      type: 'file',
      file,
    }))
  )
}

function navigate(action: UploadAction) {
  router.push({
    name: 'requestFiles',
    query: { action }
  })
}

function goToRequest() {
  router.push({
    name: 'requestFiles'
  })
}

function removeFile(index: number) {
  filesRef.value.splice(index, 1)
}

/* ===================== */
/* HELPERS */
/* ===================== */
function formatSize(bytes?: number) {
  if (!bytes) return ''
  return `${(bytes / 1024).toFixed(1)} KB`
}
</script>

<style scoped>
.card {
	&.upload-hero {
		border-radius: 12px;
		padding: 24px 32px;
		box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
	}
}

.file-preview {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.file-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--color-background-hover);
  padding: 10px 12px;
  border-radius: 8px;
}

.file-info {
  display: flex;
  flex-direction: column;
}

.file-name {
  font-weight: 500;
}

.file-size {
  font-size: 12px;
  color: var(--color-text-maxcontrast);
}

.remove-btn {
  background: transparent;
  border: none;
  color: red;
  cursor: pointer;
}

.submit-btn {
  margin-top: 10px;
  padding: 10px;
  border-radius: 8px;
  background: var(--color-primary-element);
  color: white;
  border: none;
  cursor: pointer;
}
</style>
