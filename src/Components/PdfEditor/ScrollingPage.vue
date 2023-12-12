<script>
export default {
  name: 'ScrollingPage',

  props: {
    page: {
      type: Object, // instance of PDFPageProxy returned from pdf.getPage
      required: true,
    },
    focusedPage: {
      type: Number,
      default: undefined,
    },
    scrollTop: {
      type: Number,
      default: 0,
    },
    clientHeight: {
      type: Number,
      default: 0
    },
    enablePageJump: {
      type: Boolean,
      default: false,
    },
  },

  data() {
    return {
      elementTop: 0,
      elementHeight: 0,
    };
  },

  computed: {
    isPageFocused() {
      return this.page.pageNumber === this.focusedPage;
    },

    isElementFocused() {
      const {elementTop, bottom, elementHeight, scrollTop, clientHeight} = this;
      if (!elementHeight) return;

      const halfHeight = (elementHeight / 2);
      const halfScreen = (clientHeight / 2);
      const delta = elementHeight >= halfScreen ? halfScreen : halfHeight;
      const threshold = scrollTop + delta;

      return elementTop < threshold && bottom >= threshold;
    },

    bottom() {
      return this.elementTop + this.elementHeight;
    },

    scrollBottom() {
      return this.scrollTop + this.clientHeight;
    },
  },

  methods: {
    jumpToPage() {
      if (!this.enablePageJump || this.isElementFocused || !this.isPageFocused) return;

      this.$emit('page-jump', this.elementTop);
    },

    updateElementBounds() {
      const {offsetTop, offsetHeight} = this.$el;
      this.elementTop = offsetTop;
      this.elementHeight = offsetHeight;
    },
  },

  watch: {
    scrollTop: 'updateElementBounds',
    clientHeight: 'updateElementBounds',
    isPageFocused: 'jumpToPage',
  },

  created() {
    this.$on('update-visibility', this.updateElementBounds);
  },

  mounted() {
    this.updateElementBounds();
  },

  render() {
    const {isPageFocused, isElementFocused} = this;
    return this.$scopedSlots.default({
      isPageFocused,
      isElementFocused,
    });
  },
}
</script>
