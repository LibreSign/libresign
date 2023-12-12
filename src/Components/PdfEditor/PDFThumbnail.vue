<template>
  <div
    @click="focusPage"
    v-visible.once="drawPage"
    :class="{ focused: isPageFocused }"
    class="pdf-thumbnail"
    >
    <img
      v-if="src"
      :src="src"
      class="box-shadow"
      />
    <div
      v-else
      class="placeholder box-shadow"
      >
      <div class="content">
        Loading
      </div>
    </div>
    <span class="page-number">{{ pageNumber }}</span>
  </div>
</template>

<script>
import debug from 'debug';
const log = debug('app:components/PDFThumbnail');

import visible from './directives/visible.js';

export default {
  name: 'PDFThumbnail',

  props: {
    page: {
      type: Object, // instance of PDFPageProxy returned from pdf.getPage
      required: true,
    },
    scale: {
      required: true,
    },
    isPageFocused: {
      type: Boolean,
      default: false,
    },
  },

  directives: {
    visible
  },

  data() {
    return {
      src: undefined,
    };
  },

  computed: {
    viewport() {
      return this.page.getViewport(1.0);
    },

    pageNumber() {
      return this.page.pageNumber;
    },
  },

  methods: {
    focusPage() {
      this.$emit('page-focus', this.pageNumber);
    },

    drawPage() {
      if (this.renderTask) return;

      const {viewport} = this;
      const canvas = document.createElement('canvas')
      const canvasContext = canvas.getContext('2d')
      const renderContext = { canvasContext, viewport };
      canvas.height = viewport.height;
      canvas.width = viewport.width;

      this.renderTask = this.page.render(renderContext);
      this.renderTask
        .then(() => {
          this.src = canvas.toDataURL();

          // Zeroing the width and height causes Firefox to release graphics
          // resources immediately, which can greatly reduce memory consumption.
          canvas.width = 0;
          canvas.height = 0;
        })
        .then(() => {
          this.$emit('thumbnail-rendered', {
            page: this.page,
            text: `Rendered thumbnail ${this.pageNumber}`,
          });
         })
        .catch(response => {
          this.destroyRenderTask();
          this.$emit('thumbnail-errored', {
            response,
            page: this.page,
            text: `Failed to render thumbnail ${this.pageNumber}`,
          });
        });
    },

    destroyPage(_newPage, page) {
      // PDFPageProxy#_destroy
      // https://mozilla.github.io/pdf.js/api/draft/PDFPageProxy.html
      if (page) page._destroy();

      this.destroyRenderTask();
    },

    destroyRenderTask() {
      if (!this.renderTask) return;

      // RenderTask#cancel
      // https://mozilla.github.io/pdf.js/api/draft/RenderTask.html
      this.renderTask.cancel();
      delete this.renderTask;
    },

    updateVisibility() {
      this.$parent.$emit('update-visibility');
    },
  },

  watch: {
    page: 'destroyPage',
    src: 'updateVisibility',
    scale: 'updateVisibility',
  },

  mounted() {
    log(`Page ${this.pageNumber} mounted`);
  },

  beforeDestroy() {
    this.destroyPage(undefined, this.page);
  },
}
</script>

<style scoped>
.pdf-thumbnail {
  cursor: pointer;
  margin: 0 auto;
  display: flex;
  justify-content: center;
  align-items: center;
  flex-direction: column;
  position: relative;
  width: 100%;
}

img,
.placeholder {
  border: 7px solid transparent;
  border-radius: 5px;
  width: 65%;
}

.pdf-thumbnail.focused img {
  border-color: rgba(68, 204, 139, 0.65);
}

.placeholder {
  background: white;
  background-clip: content-box;
  position: relative;
}

.placeholder:before {
  content: '';
  display: block;
  padding-top: 75%;
}

.placeholder .content {
  display: flex;
  justify-content: center;
  align-items: center;
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
}

.page-number {
  color: white;
  font-weight: bold;
}
</style>
