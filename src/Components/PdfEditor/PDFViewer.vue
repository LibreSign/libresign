<template>
  <div class="pdf-viewer">
    <header class="pdf-viewer__header box-shadow">
      <div class="pdf-preview-toggle">
        <a @click.prevent.stop="togglePreview" class="icon"><PreviewIcon /></a>
      </div>

      <PDFZoom
        :scale="scale"
        @change="updateScale"
        @fit="updateFit"
        class="header-item"
        />

      <PDFPaginator
        v-model="currentPage"
        :pageCount="pageCount"
        class="header-item"
        />

      <slot name="header"></slot>
    </header>

    <PDFData
      class="pdf-viewer__main"
      :url="url"
      @page-count="updatePageCount"
      @page-focus="updateCurrentPage"
      @document-rendered="onDocumentRendered"
      @document-errored="onDocumentErrored"
      >
      <template v-slot:preview="{pages}">
        <PDFPreview
          v-show="isPreviewEnabled"
          class="pdf-viewer__preview"
          v-bind="{pages, scale, currentPage, pageCount, isPreviewEnabled}"
          />
      </template>

      <template v-slot:document="{pages}">
        <PDFDocument
          class="pdf-viewer__document"
          :class="{ 'preview-enabled': isPreviewEnabled }"
          v-bind="{pages, scale, optimalScale, fit, currentPage, pageCount, isPreviewEnabled}"
          @scale-change="updateScale"
          />
      </template>
    </PDFData>
  </div>
</template>

<script>
import PreviewIcon from './assets/icon-preview.svg';

import PDFDocument from './PDFDocument.vue';
import PDFData from './PDFData.vue';
import PDFPaginator from './PDFPaginator.vue';
import PDFPreview from './PDFPreview.vue';
import PDFZoom from './PDFZoom.vue';

function floor(value, precision) {
  const multiplier = Math.pow(10, precision || 0);
  return Math.floor(value * multiplier) / multiplier;
}

export default {
  name: 'PDFViewer',

  components: {
    PDFDocument,
    PDFData,
    PDFPaginator,
    PDFPreview,
    PDFZoom,
    PreviewIcon,
  },

  props: {
    url: String,
  },

  data() {
    return {
      scale: undefined,
      optimalScale: undefined,
      fit: undefined,
      currentPage: 1,
      pageCount: undefined,
      isPreviewEnabled: false,
    };
  },

  methods: {
    onDocumentRendered() {
      this.$emit('document-errored', this.url);
    },

    onDocumentErrored(e) {
      this.$emit('document-errored', e);
    },

    updateScale({scale, isOptimal = false}) {
      const roundedScale = floor(scale, 2);
      if (isOptimal) this.optimalScale = roundedScale;
      this.scale = roundedScale;
    },

    updateFit(fit) {
      this.fit = fit;
    },

    updatePageCount(pageCount) {
      this.pageCount = pageCount;
    },

    updateCurrentPage(pageNumber) {
      this.currentPage = pageNumber;
    },

    togglePreview() {
      this.isPreviewEnabled = !this.isPreviewEnabled;
    },
  },

  watch: {
    url() {
      this.currentPage = undefined;
    },
  },

  mounted() {
    document.body.classList.add('overflow-hidden');
  },
};
</script>

<style scoped>
header {
  display: flex;
  justify-content: center;
  align-items: center;
  flex-wrap: wrap;
  padding: 1em;
  position: relative;
  z-index: 99;
}
.header-item {
  margin: 0 2.5em;
}

.pdf-viewer .pdf-viewer__document,
.pdf-viewer .pdf-viewer__preview {
  top: 70px;
}

.pdf-viewer__preview {
  display: block;
  width: 15%;
  right: 85%;
}

.pdf-viewer__document {
  top: 70px;
  width: 100%;
  left: 0;
}

.pdf-viewer__document.preview-enabled {
  width: 85%;
  left: 15%;
}

@media print {
  header {
    display: none;
  }
}
</style>
