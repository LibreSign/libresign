<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<VuePdfEditor ref="vuePdfEditor"
		width="100%"
		height="100%"
		class="vue-pdf-editor"
		:show-choose-file-btn="false"
		:show-customize-editor="false"
		:show-line-size-select="false"
		:show-font-size-select="false"
		:show-font-select="false"
		:show-rename="false"
		:show-save-btn="false"
		:save-to-upload="false"
		:init-file-src="fileSrc"
		:init-image-scale="1"
		:seal-image-show="false"
		@pdf-editor:end-init="endInit">
		<template #custom="{ object, pagesScale, pageWidth, pageHeight }">
			<Signature :x="object.x"
				:y="object.y"
				:fix-size="object.signer.readOnly"
				:read-only="object.signer.readOnly"
				:display-name="object.signer.displayName"
				:width="object.width"
				:height="object.height"
				:origin-width="object.originWidth"
				:origin-height="object.originHeight"
				:page-scale="pagesScale"
				:page-width="pageWidth"
				:page-height="pageHeight"
				@onUpdate="$refs.vuePdfEditor.updateObject(object.id, $event)"
				@onDelete="onDeleteSigner(object)" />
		</template>
	</VuePdfEditor>
</template>

<script>
// eslint-disable-next-line import/default
import VuePdfEditor from '@libresign/vue-pdf-editor'

import Signature from './Signature.vue'

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
		readOnly: {
			type: Boolean,
			default: false,
		},
	},
	methods: {
		endInit(event) {
			this.$emit('pdf-editor:end-init', { ...event })
		},
		onDeleteSigner(object) {
			this.$emit('pdf-editor:on-delete-signer', object)
			this.$refs.vuePdfEditor.deleteObject(object.id)
		},
		addSigner(signer) {
			const object = {
				id: this.$refs.vuePdfEditor.genID(),
				type: 'custom',
				signer,
				width: signer.element.coordinates.width,
				height: signer.element.coordinates.height,
				originWidth: signer.element.coordinates.width,
				originHeight: signer.element.coordinates.height,
				x: signer.element.coordinates.llx,
				y: signer.element.coordinates.ury,
				pageWidth: this.$refs.vuePdfEditor.pageSizes[this.$refs.vuePdfEditor.selectedPageIndex + 1].width,
				pageHeight: this.$refs.vuePdfEditor.pageSizes[this.$refs.vuePdfEditor.selectedPageIndex + 1].height,
			}
			this.$refs.vuePdfEditor.allObjects = this.$refs.vuePdfEditor.allObjects.map((objects, pIndex) => {
				if (pIndex === signer.element.coordinates.page - 1) {
					return [...objects, object]
				}
				return objects
			})
		},
	},
}
</script>
<style>
/** @todo remove this, only necessary because VuePdfEditor use Tailwind and the Tailwind have a global CSS that affect this */
audio, canvas, embed, iframe, img, object, svg, video {
	display: unset;
}

canvas {
	border-bottom: 2px solid #eee;
}
</style>

<style lang="scss" scoped>
.vue-pdf-editor {
	overflow: unset !important;
	min-height: 0;
	position: unset !important;
	display: flex;
}
</style>
