<template>
  <ScrollingDocument
    class="pdf-preview"
    @pages-fetch="onPagesFetch"
    v-bind="{pages, pageCount, currentPage}"
    v-slot="{page, isPageFocused}"
    :is-parent-visible="isPreviewEnabled"
    >
    <PDFThumbnail
      v-bind="{scale, page, isPageFocused}"
      @thumbnail-rendered="onThumbnailRendered"
      @thumbnail-errored="onThumbnailErrored"
      @page-focus="onPageFocused"
      />
  </ScrollingDocument>
</template>

<script>
import ScrollingDocument from './ScrollingDocument.vue';
import PDFThumbnail from './PDFThumbnail.vue';

export default {
  name: 'PDFPreview',

  components: {
    ScrollingDocument,
    PDFThumbnail,
  },

  props: {
    pages: {
      required: true,
    },
    pageCount: {
      type: Number,
      default: 0,
    },
    scale: {
      type: Number,
      default: 1.0,
    },
    currentPage: {
      type: Number,
      default: 1,
    },
    isPreviewEnabled: {
      default: false,
    },
  },

  methods: {
    onPagesFetch(currentPage) {
      this.$parent.$emit('pages-fetch', currentPage);
    },

    onPageFocused(pageNumber) {
      this.$parent.$emit('page-focus', pageNumber);
    },

    onThumbnailRendered(payload) {
      this.$el.dispatchEvent(new Event('scroll'));
      this.$parent.$emit('thumbnail-rendered', payload);
    },

    onThumbnailErrored(payload) {
      this.$parent.$emit('thumbnail-errored', payload);
    },
  },
};
</script>

<style scoped>
.pdf-preview {
  position: absolute;
  overflow: auto;
  z-index: 1;
  padding: 2em 0;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
}

.scrolling-page {
  margin-bottom: 1em;
}

@media print {
  .pdf-preview {
    display: none;
  }
}
</style>
