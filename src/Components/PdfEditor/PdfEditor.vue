<template>
	<VuePdfEditor ref="vuePdfEditor"
		width="100%"
		height="100%"
		:show-choose-file-btn="false"
		:show-customize-editor="false"
		:show-line-size-select="false"
		:show-font-size-select="false"
		:show-font-select="false"
		:show-save-btn="false"
		:save-to-upload="false"
		:init-file-src="fileSrc"
		:init-image-scale="1"
		:seal-image-show="false"
		@pdf-editor:end-init="endInit">
		<template #custom="{ object, pagesScale }">
			<Signature :x="object.x"
				:y="object.y"
				:fix-size="false"
				:display-name="object.signer.displayName"
				:width="object.width"
				:height="object.height"
				:origin-width="object.originWidth"
				:origin-height="object.originHeight"
				:page-scale="pagesScale"
				@onUpdate="$refs.vuePdfEditor.updateObject(object.id, $event)"
				@onDelete="$refs.vuePdfEditor.deleteObject(object.id)" />
		</template>
	</VuePdfEditor>
</template>

<script>
import VuePdfEditor from '@libresign/vue-pdf-editor'
import Signature from './Signature.vue'
import { SignatureImageDimensions } from './../Draw/options.js'

export default {
	name: 'PdfEditor',
	components: {
		VuePdfEditor,
		Signature,
	},
	props: {
		fileSrc: {
			type: String,
			default: '',
			require: true,
		},
	},
	methods: {
		endInit(event) {
			this.$emit('pdf-editor:end-init', { ...event })
		},
		addSigner(signer) {
			const width = SignatureImageDimensions.width
			const height = SignatureImageDimensions.height

			const object = {
				id: this.$refs.vuePdfEditor.genID(),
				type: 'custom',
				signer,
				width,
				height,
				originWidth: width,
				originHeight: height,
				x: 0,
				y: 0,
			}
			this.$refs.vuePdfEditor.addObject(object)
		},
	},
}
</script>
