<template>
	<div class="container">
		<ul class="editor-select">
			<li
				v-if="textEditor"
				:class="{active: isActive('text')}"
				@click.prevent="setActive('text')">
				<img :src="$options.icons.textIcon" :alt="t('libresign', 'Text')">
				Text
			</li>
			<li
				v-if="drawEditor"
				:class="{active: isActive('draw')}"
				@click.prevent="setActive('draw')">
				<img :src="$options.icons.drawnIcon" :alt="t('libresign', 'Draw')">
				Draw
			</li>
			<li
				v-if="fileEditor"
				:class="{active: isActive('upload')}"
				@click.prevent="setActive('upload')">
				<img :src="$options.icons.uploadIcon" :alt="t('libresign', 'Upload')">
				Upload
			</li>
		</ul>

		<div class="content">
			<Editor
				v-if="isActive('draw')"
				:class="{'active show': isActive('draw')}"
				@close="close"
				@save="save" />
			<TextInput
				v-if="isActive('text')"
				ref="text"
				:class="{'active show': isActive('text')}"
				@save="save"
				@close="close" />
			<FileUpload
				v-if="isActive('upload')"
				:class="{'active show': isActive('upload')}"
				@save="save"
				@close="close" />
		</div>
	</div>
</template>

<script>
import Editor from './Editor.vue'
import TextInput from './TextInput.vue'
import FileUpload from './FileUpload.vue'
import DrawIcon from '../../assets/images/curvature.png'
import TextIcon from '../../assets/images/text.png'
import UploadIcon from '../../assets/images/upload-black.png'

export default {
	name: 'Draw',
	components: { TextInput, Editor, FileUpload },
	props: {
		drawEditor: {
			type: Boolean,
			required: false,
			default: true,
		},
		textEditor: {
			type: Boolean,
			required: false,
			default: false,
		},
		fileEditor: {
			type: Boolean,
			required: false,
			default: false,
		},
	},

	icons: {
		drawnIcon: DrawIcon,
		textIcon: TextIcon,
		uploadIcon: UploadIcon,
	},

	data: () => ({
		toolSelected: 'draw',
	}),

	methods: {
		isActive(tabItem) {
			return this.toolSelected === tabItem
		},
		close() {
			this.$emit('close')
		},
		save(param) {
			this.$emit('save', param)
		},
		setActive(tabItem) {
			this.toolSelected = tabItem

			if (tabItem === 'text') {
				this.$nextTick(() => this.$refs.text.setFocus())
			}
		},
	},
}
</script>
<style lang="scss" scoped>
ul.editor-select {
	margin: 0;
	li {
		display: inline-block;
		padding: 10px;
		margin-bottom: -1px;
		position: relative;

		img{
			max-width: 14px;
			margin-right: 10px;
		}

		&.active{
			border: 1px solid #dbdbdb;
			border-bottom-color:#ffffff;
			border-radius: 5px 5px 0 0;
		}
	}
}

.container{
	display: flex;
	flex-direction: column;
	width: 380px;
	height: 100%;
	margin-top: 10px;

	.content{
		width: 100%;
		border: 1px solid #dbdbdb;
	}
}
</style>
