<script>
export default {
  name: "PannableAreaMixin",
  data() {
    return {
      x_mixin: null,
      y_mixin: null,
    }
  },
  mounted() {
    // this.$refs.canvasElement.addEventListener('mousedown', this.handleMousedown)
    // this.$refs.canvasElement.addEventListener('touchstart', this.handleTouchStart)
  },
  beforeDestroy() {
    // this.$refs.canvasElement.removeEventListener('mousedown', this.handleMousedown)
    // this.$refs.canvasElement.removeEventListener('touchstart', this.handleTouchStart)
  },
  created() {},
  methods: {
    handleMousedown(event) {
      this.x_mixin = event.clientX
      this.y_mixin = event.clientY
      const target = event.target
      window.addEventListener("mousemove", this.handlePanMove)
      window.addEventListener("mouseup", this.handlePanEnd)
      return {
        detail: { x: this.x_mixin, y: this.y_mixin, target },
      }
    },

    handleMousemove(event) {
      const dx = event.clientX - this.x_mixin
      const dy = event.clientY - this.y_mixin
      this.x_mixin = event.clientX
      this.y_mixin = event.clientY

      return {
        detail: { x: this.x_mixin, y: this.y_mixin, dx, dy },
      }
    },

    handleMouseup(event) {
      this.x_mixin = event.clientX
      this.y_mixin = event.clientY
      window.removeEventListener("mousemove", this.handlePanMove)
      window.removeEventListener("mouseup", this.handlePanEnd)
      return {
        detail: { x: this.x_mixin, y: this.y_mixin },
      }
    },
    handleTouchStart(event) {
      if (event.touches.length > 1) return
      const touch = event.touches[0]
      this.x_mixin = touch.clientX
      this.y_mixin = touch.clientY
      const target = touch.target

      window.addEventListener("touchmove", this.handlePanMove, {
        passive: false,
      })
      window.addEventListener("touchend", this.handlePanEnd)
      return {
        detail: { x: this.x_mixin, y: this.y_mixin, target },
      }
    },
    handleTouchmove(event) {
      if (event.touches.length > 1) return
      const touch = event.touches[0]
      const dx = touch.clientX - this.x_mixin
      const dy = touch.clientY - this.y_mixin
      this.x_mixin = touch.clientX
      this.y_mixin = touch.clientY

      return {
        detail: { x: this.x_mixin, y: this.y_mixin, dx, dy },
      }
    },
    handleTouchend(event) {
      const touch = event.changedTouches[0]
      this.x_mixin = touch.clientX
      this.y_mixin = touch.clientY

      window.removeEventListener("touchmove", this.handlePanMove)
      window.removeEventListener("touchend", this.handlePanEnd)
      return {
        detail: { x: this.x_mixin, y: this.y_mixin },
      }
    },
  },
}
</script>

