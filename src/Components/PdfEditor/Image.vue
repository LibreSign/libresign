<template>
  <div
      class="container"
      :style="{
	  		width: `${width + dw}px`,
		  	height: `${Math.round((width + dw) / ratio)}px`,
			  transform: `translate(${x + dx}px, ${y + dy}px)`,
  		}"
  >
    <div
        class="container-grab"
        @mousedown="handlePanStart"
        @touchstart="handlePanStart"
    >
      <div v-if="!fixSize"
        data-direction="left-top"
        class="section-selector-top"
      />
      <div v-if="!fixSize"
        data-direction="right-top"
        class="section-selector-left"
      />
      <div v-if="!fixSize"
        data-direction="left-bottom"
        class="section-selector-right"
      />
      <div v-if="!fixSize"
        data-direction="right-bottom"
        class="section-selector-right"
      />
    </div>
    <NcActionButton @click="onDelete">
		  <template #icon>
				<Delete :size="20" />
			</template>
		</NcActionButton>
    <canvas
      ref="imgCanvas"
      class="image-content"
    />
  </div>
</template>

<script>
import itemEventsMixin from './ItemEventsMixin.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'

export default {
  name: 'ImageComponent',
  mixins: [itemEventsMixin],
  components: {
    Delete,
    NcActions,
  },
  props: [
    "payload",
    "file",
    "width",
    "height",
    "originWidth",
    "originHeight",
    "x",
    "y",
    "pageScale",
    "fixSize"
  ],
  data() {
    return {
      startX: null,
      startY: null,
      canvas: null,
      operation: "",
      directions: [],
      dx: 0,
      dy: 0,
      dw: 0,
      dh: 0,
    }
  },
  computed: {
    ratio() {
      return this.originWidth / this.originHeight
    },
  },
  watch: {
    async file(value) {
      value && (await this.render())
    },
  },
  async mounted() {
    await this.render()
  },
  methods: {
    async render() {
      // use canvas to prevent img tag's auto resize
      const canvas = this.$refs.imgCanvas
      canvas.width = this.originWidth
      canvas.height = this.originHeight
      canvas.getContext("2d").drawImage(this.payload, 0, 0)
      let scale = 1
      const MAX_TARGET = 500
      if (this.width > MAX_TARGET) {
        scale = MAX_TARGET / this.width
      }
      if (this.height > MAX_TARGET) {
        scale = Math.min(scale, MAX_TARGET / this.height)
      }
      this.$emit("onUpdate", {
        width: this.width * scale,
        height: this.height * scale,
      })
      if (
          !["image/jpeg", "image/png", "image/webp"].includes(
              this.file.type,
          )
      ) {
        canvas.toBlob((blob) => {
          this.$emit("onUpdate", {
            file: blob,
          })
        })
      }
    },
    handlePanMove(event) {
      let coordinate;
      if (event.type === "mousemove") {
        coordinate = this.handleMousemove(event)
      }
      if (event.type === "touchmove") {
        coordinate = this.handleTouchmove(event)
      }
      if (!coordinate) return console.log("ERROR")

      const _dx = (coordinate.detail.x - this.startX) / this.pageScale
      const _dy = (coordinate.detail.y - this.startY) / this.pageScale
      if (this.operation === "move") {
        this.dx = _dx
        this.dy = _dy
      } else if (this.operation === "scale") {
        if (this.directions.includes("left")) {
          this.dx = _dx
          this.dw = -_dx
        }
        if (this.directions.includes("top")) {
          this.dy = _dy
          this.dh = -_dy
        }
        if (this.directions.includes("right")) {
          this.dw = _dx
        }
        if (this.directions.includes("bottom")) {
          this.dh = _dy
        }
      }
    },
    handlePanEnd(event) {
      let coordinate
      if (event.type === "mouseup") {
        coordinate = this.handleMouseup(event)
      }
      if (event.type === "touchend") {
        coordinate = this.handleTouchend(event)
      }
      if (!coordinate) return console.log("ERROR")
      if (this.operation === "move") {
        this.$emit("onUpdate", {
          x: this.x + this.dx,
          y: this.y + this.dy,
        })
        this.dx = 0
        this.dy = 0
      } else if (this.operation === "scale") {
        this.$emit("onUpdate", {
          x: this.x + this.dx,
          y: this.y + this.dy,
          width: this.width + this.dw,
          height: Math.round((this.width + this.dw) / this.ratio),
        })
        this.dx = 0
        this.dy = 0
        this.dw = 0
        this.dh = 0
        this.directions = []
      }
      this.operation = ""
    },
    handlePanStart(event) {
      let coordinate
      if (event.type === "mousedown") {
        coordinate = this.handleMousedown(event)
      }
      if (event.type === "touchstart") {
        coordinate = this.handleTouchStart(event)
      }
      if (!coordinate) return console.log("ERROR")

      this.startX = coordinate.detail.x
      this.startY = coordinate.detail.y
      if (coordinate.detail.target === event.currentTarget) {
        return (this.operation = "move")
      }
      this.operation = "scale"
      this.directions =
          coordinate.detail.target.dataset.direction.split("-")
    },
    onDelete() {
      this.$emit("onDelete");
    },
  },
}
</script>

<style scoped>
.container {
  position: absolute;
  top: 0;
  left: 0;
}

.container-grab {
  position: absolute;
  width: 100%;
  height: 100%;
  cursor: grab;
}

.operation {
  background-color: rgba(0, 0, 0, 0.3);
}

.selector {
  border-radius: 10px;
  width: 12px;
  height: 12px;
  margin-left: -6px;
  margin-top: -6px;
  background-color: #32b5fe;
  border: 1px solid #32b5fe;
}

.section-selector-top {
  top: 0%;
  left: 0%;
  position: absolute;
  cursor: nwse-resize;
}

.section-selector-left {
  top: 0%;
  left: 100%;
  position: absolute;
  cursor: nesw-resize;
}

.section-selector-right {
  top: 0%;
  left: 0%;
  position: absolute;
  cursor: nesw-resize;
}

.container-delete {
  top: 0%;
  left: 50%;
}

.image-content {
  border-width: 1px;
  border-color: #9CA3AF;
  border-style: dashed;
  width: 100%;
  height: 100%;
}

.delete {
  border-radius: 10px;
  width: 18px;
  height: 18px;
  margin-left: -9px;
  margin-top: -9px;
  background-color: #ffffff;
}
</style>
