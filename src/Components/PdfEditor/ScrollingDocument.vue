<template>
  <div
    class="scrolling-document"
    v-scroll.immediate="updateScrollBounds"
    >
    <ScrollingPage
      v-for="page in pages"
      :key="page.pageNumber"
      v-bind="{page, clientHeight, scrollTop, focusedPage, enablePageJump}"
      v-slot="{isPageFocused, isElementFocused}"
      @page-jump="onPageJump"
      >
      <div
        class="scrolling-page"
        >
        <slot v-bind="{page, isPageFocused, isElementFocused}"></slot>
      </div>
    </ScrollingPage>

    <div v-visible="fetchPages" class="observer"></div>
  </div>
</template>

<script>
import scroll from './directives/scroll.js';
import visible from './directives/visible.js';

import ScrollingPage from './ScrollingPage.vue';

export default {
  components: {
    ScrollingPage,
  },

  directives: {
    visible,
    scroll,
  },

  props: {
    pages: {
      required: true,
    },
    enablePageJump: {
      type: Boolean,
      default: false,
    },
    currentPage: {
      type: Number,
      default: 1,
    },
    isParentVisible: {
      default: true,
    },
  },

  data() {
    return {
      focusedPage: undefined,
      scrollTop: 0,
      clientHeight: 0,
    };
  },

  computed: {
    pagesLength() {
      return this.pages.length;
    },
  },

  methods: {
    fetchPages(currentPage) {
      this.$emit('pages-fetch', currentPage);
    },

    onPageJump(scrollTop) {
      this.$emit('page-jump', scrollTop);
    },

    updateScrollBounds() {
      const {scrollTop, clientHeight} = this.$el;
      this.scrollTop = scrollTop;
      this.clientHeight = clientHeight;
    },
  },

  watch: {
    isParentVisible: 'updateScrollBounds',

    pagesLength(count, oldCount) {
      if (oldCount === 0) this.$emit('pages-reset');

      // Set focusedPage after new pages are mounted
      this.$nextTick(() => {
        this.focusedPage = this.currentPage;
      });
    },

    currentPage(currentPage) {
      if (currentPage > this.pages.length) {
        this.fetchPages(currentPage);
      } else {
        this.focusedPage = currentPage;
      }
    },
  },
}
</script>
