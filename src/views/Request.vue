<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="request-page">

		<!-- PAGE HEADER -->
		<header class="request-header">
			<h1>Request Signature</h1>
			<p>
				Upload your document to begin signature request workflow
			</p>
		</header>

		<main class="request-content">
			<!-- DOCUMENTS -->
			<DocumentList v-if="hasFiles" />

			<!-- PICKER (ONLY ENTRY POINT) -->
			<RequestPicker v-else />
		</main>

	</div>
</template>
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

import { useFilesStore } from '../store/files.js'
import { useSidebarStore } from '../store/sidebar.js'
import { loadState } from '@nextcloud/initial-state'
import RequestPicker from '@/components/Request/RequestPicker.vue'
import DocumentList from '@/components/Request/DocumentList.vue'

defineOptions({
	name: 'Request',
})

type SidebarStore = {
	isVisible: boolean
}

const filesStore = useFilesStore()
const sidebarStore = useSidebarStore() as SidebarStore

/* ===================== */
/* STATE */
/* ===================== */
const hasFiles = computed(() => {
  return Object.keys(filesStore.files).length > 0
})

onMounted(() => {
	filesStore.disableIdentifySigner()
})

onBeforeUnmount(() => {
	filesStore.selectFile()
})

defineExpose({
	filesStore,
	sidebarStore,
})
</script>

<style lang="scss" scoped>
.request-page {
  max-width: 1100px;
  margin: 0 auto;
  padding: 40px 24px;
}

.request-header {
  text-align: center;
  margin-bottom: 32px;

  h1 {
    font-size: 32px;
    font-weight: 700;
  }

  p {
    margin-top: 10px;
    font-size: 15px;
    color: var(--color-text-maxcontrast);
  }
}

.request-content {
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.picker-muted {
  opacity: 0.5;
  transform: scale(0.98);
  transition: all 0.2s ease;
}
</style>
