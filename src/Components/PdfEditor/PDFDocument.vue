<template>
  <ScrollingDocument
    class="pdf-document"
    v-bind="{pages, pageCount, currentPage}"
    v-slot="{page, isPageFocused, isElementFocused}"
    :enable-page-jump="true"
    @page-jump="onPageJump"
    @pages-fetch="onPagesFetch"
    @pages-reset="fitWidth"
    >
    <PDFPage
      v-bind="{scale, optimalScale, page, isPageFocused, isElementFocused}"
      @page-rendered="onPageRendered"
      @page-errored="onPageErrored"
      @page-focus="onPageFocused"
    />
  </ScrollingDocument>
</template>

<script>
// PDFDocument renders an entire PDF inline using
// PDF.js and <canvas>. Currently does not support,
// rendering of selected pages (but could be easily
// updated to do so).
import {PIXEL_RATIO, VIEWPORT_RATIO} from './utils/constants.js';

import ScrollingDocument from './ScrollingDocument.vue';
import PDFPage from './PDFPage.vue';

export default {
  name: 'PDFDocument',

  components: {
    ScrollingDocument,
    PDFPage,
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
    optimalScale: {
      type: Number,
    },
    fit: {
      type: String,
    },
    currentPage: {
      type: Number,
      default: 1,
    },
    isPreviewEnabled: {
      default: false,
    },
  },

  computed: {
    defaultViewport() {
      if (!this.pages.length) return {width: 0, height:0};
      const [page] = this.pages;

      return page.getViewport(1.0);
    },

    isPortrait() {
      const {width, height} = this.defaultViewport;
      return width <= height;
    },
  },

  methods: {
    pageWidthScale() {
      const {defaultViewport, $el} = this;
      if (!defaultViewport.width) return 0;

      return ($el.clientWidth * PIXEL_RATIO) * VIEWPORT_RATIO / defaultViewport.width;
    },

    pageHeightScale() {
      const {defaultViewport, $el} = this;
      if (!defaultViewport.height) return 0;

      return ($el.clientHeight * PIXEL_RATIO) * VIEWPORT_RATIO / defaultViewport.height;
    },
    // Determine an ideal scale using viewport of document's first page, the pixel ratio from the browser
    // and a subjective scale factor based on the screen size.
    fitWidth() {
      const scale = this.pageWidthScale();
      this.updateScale(scale, {isOptimal: !this.optimalScale});
    },

    fitHeight() {
      const scale = this.isPortrait ? this.pageHeightScale() : this.pageWidthScale();
      this.updateScale(scale);
    },

    fitAuto() {
      const scale = Math.min(this.pageWidthScale(), this.pageHeightScale());
      this.updateScale(scale);
    },

    updateScale(scale, {isOptimal = false} = {}) {
      if (!scale) return;
      this.$emit('scale-change', {scale, isOptimal});
    },

    onPageJump(scrollTop) {
      this.$el.scrollTop = scrollTop; // triggers 'scroll' event
    },

    onPagesFetch(currentPage) {
      this.$parent.$emit('pages-fetch', currentPage);
    },

    onPageFocused(pageNumber) {
      this.$parent.$emit('page-focus', pageNumber);
    },

    onPageRendered(payload) {
      this.$parent.$emit('page-rendered', payload);
    },

    onPageErrored(payload) {
      this.$parent.$emit('page-errored', payload);
    },
  },

  watch: {
    fit(fit) {
      switch (fit) {
        case 'width':
          this.fitWidth();
          break;

        case 'auto':
          this.fitAuto();
          break;

        default:
          break;
      }
    },
    pageCount: 'fitWidth',
    isPreviewEnabled: 'fitWidth',
  },
};
</script>

<style>
.pdf-document {
  position: absolute;
  overflow: auto;
  width: 100%;
  top: 0;
  bottom: 0;
  left: 0;
  right: 0;
  background: #525f69;
}

.scrolling-page {
  margin-bottom: 1em;
}

@media print {
  .pdf-document {
    position: static;
  }
}
</style>
