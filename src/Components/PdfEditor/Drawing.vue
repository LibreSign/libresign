<template>
  <div>
  	<button v-if="!isDrawing" class="primary publish-btn" @click="drawing">
	  	{{ t('libresign', 'Add Signature') }}
  	</button>
    <div v-if="isDrawing" class="container-drawing">
      <VueSignaturePad
        width="500px"
        height="500px"
        :customStyle="{ border: 'black 3px solid', background: '#FFF' }"
        ref="signaturePad"
      />
      <div>
        <button @click="save">Save</button>
        <button @click="undo">Undo</button>
        <button @click="drawing">Cancel</button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Drawing',
  data() {
    return {
      isDrawing: false,
    }
  },
  methods: {
    undo() {
      this.$refs.signaturePad.undoSignature();
    },
    save() {
      const { isEmpty, data } = this.$refs.signaturePad.saveSignature();
      console.log(isEmpty);
      console.log(data);
      this.drawing()
    },
    drawing() {
      this.isDrawing = !this.isDrawing
    }
  }
};
</script>
