<template>
  <div>
    <slot />
  </div>
</template>

<script>
export default {
  name: "TapoutComponent",
  mounted() {
    window.addEventListener("touchstart", this.handleTapoutTouch)
    window.addEventListener("mousedown", this.handleTapoutMouse)
    this.$el.addEventListener("mousedown", this.handleFocus)
    this.$el.addEventListener("touchstart", this.handleFocus)
  },
  beforeDestroy() {
    window.removeEventListener("touchstart", this.handleTapoutTouch)
    window.removeEventListener("mousedown", this.handleTapoutMouse)
    this.$el.removeEventListener("mousedown", this.handleFocus)
    this.$el.removeEventListener("touchstart", this.handleFocus)
  },
  methods: {
    handleTapoutMouse(event) {
      if (!this.$el.contains(event.target)) {
        this.$emit("tapout")
      }
    },
    handleTapoutTouch(event) {
      if (
          !Array.from(event.touches).some((touch) =>
              this.$el.contains(touch.target),
          )
      ) {
        this.$emit("tapout")
      }
    },
    handleFocus() {
      this.$emit("onfocus")
    },
  },
}
</script>

<style scoped></style>
