<template>
  <div ref="portal">
    <slot />
  </div>
</template>

<script>
export default {
  name: "PortalComponent",
  data() {
    return {
      // portal: null,
    }
  },
  mounted() {
    this.$refs.portal && document.body.appendChild(this.$refs.portal)
  },
}
</script>

<style scoped></style>
