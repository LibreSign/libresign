<template>
  <section class="document-list">

    <!-- HEADER -->
    <div class="document-header">
      <h2>
        Documents
        <span class="count">{{ count }}</span>
      </h2>
    </div>

    <!-- GRID -->
    <div class="document-grid">
      <DocumentItem
			v-for="file in fileList"
			:key="file.id"
			:file="file"
			:is-active="file.id === selectedFileId"
			@select="handleSelect"
		/>
    </div>

  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { useFilesStore } from '@/store/files'
import { useSidebarStore } from '@/store/sidebar'
import DocumentItem from './DocumentItem.vue'

const filesStore = useFilesStore()
const sidebarStore = useSidebarStore()
/* ===================== */
/* STATE */
/* ===================== */
const selectedFileId = computed(() => filesStore.selectedFileId)

const fileList = computed(() => {
  return Object.values(filesStore.files || {})
})

const count = computed(() => fileList.value.length)

/* ===================== */
/* ACTIONS */
/* ===================== */
function handleSelect(id?: number | string) {
  console.log('select', id)
  if (typeof id !== 'number') return
  filesStore.selectFile(id)
  sidebarStore.activeRequestSignatureTab()
}
</script>

<style scoped>
.document-list {
  width: 100%;
}

/* HEADER */
.document-header {
  margin-bottom: 16px;
}

.document-header h2 {
  font-size: 18px;
  font-weight: 600;
}

.count {
  font-size: 14px;
  color: var(--color-text-maxcontrast);
  margin-left: 6px;
}

/* GRID */
.document-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 16px;
}

/* MOBILE */
@media (max-width: 768px) {
  .document-grid {
    grid-template-columns: 1fr;
  }
}
</style>
