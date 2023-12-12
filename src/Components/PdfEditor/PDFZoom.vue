<template>
  <div class="pdf-zoom">
    <a @click.prevent.stop="zoomIn" class="icon" :disabled="isDisabled"><ZoomInIcon /></a>
    <a @click.prevent.stop="zoomOut" class="icon" :disabled="isDisabled"><ZoomOutIcon /></a>
    <a @click.prevent.stop="fitWidth" class="icon" :disabled="isDisabled"><ExpandIcon /></a>
    <a @click.prevent.stop="fitAuto" class="icon" :disabled="isDisabled"><ShrinkIcon /></a>
  </div>
</template>

<script>
import ZoomInIcon from './assets/icon-zoom-in.svg';
import ZoomOutIcon from './assets/icon-zoom-out.svg';
import ExpandIcon from './assets/icon-expand.svg';
import ShrinkIcon from './assets/icon-shrink.svg';

export default {
  name: 'PDFZoom',

  components: {
    ZoomInIcon,
    ZoomOutIcon,
    ExpandIcon,
    ShrinkIcon,
  },

  props: {
    scale: {
      type: Number,
    },
    increment: {
      type: Number,
      default: 0.25,
    },
  },

  computed: {
    isDisabled() {
      return !this.scale;
    },
  },

  methods: {
    zoomIn() {
      this.updateScale(this.scale + this.increment);
    },

    zoomOut() {
      if (this.scale <= this.increment) return;
      this.updateScale(this.scale - this.increment);
    },

    updateScale(scale) {
       //this.$emit('change', {scale})
    },

    fitWidth() {
      this.$emit('fit', 'width');
    },

    fitAuto() {
      this.$emit('fit', 'auto');
    },
  },
}
</script>

<style>
.pdf-zoom a {
  float: left;
  cursor: pointer;
  display: block;
  border: 1px #333 solid;
  background: white;
  color: #333;
  font-weight: bold;
  line-height: 1.5em;
  width: 1.5em;
  height: 1.5em;
  font-size: 1.5em;
}
</style>
