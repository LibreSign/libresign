<template>
	<div v-show="enabled"
		v-if="isMobile"
		id="containerTools"
		ref="containerTools"
		class="v-container-tools"
		style="position: absolute"
		:style="{top: position.top+'px', left: position.left+'px'}">
		<header v-show="isMobile">
			<h1>{{ document.name }}</h1>
		</header>
		<div v-show="!signSelected" class="content-actions-tools">
			<button v-show="isMobile" class="primary" @click="handleSignPreview(true)">
				{{ t('libresign', 'Sign') }}
			</button>
			<button>
				{{ t('libresign', 'Insert Signature and/or Initials') }}
			</button>
		</div>

		<div v-if="signSelected" class="content-tools-sign">
			<Sign :pfx="getHasPfx" @sign:document="signDocument">
				<template #actions>
					<button class="" @click="handleSignPreview(false)">
						{{ t('libresign', 'Return') }}
					</button>
				</template>
			</Sign>
		</div>
	</div>
</template>

<script>
import Sign from '../Sign'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'
import { mapGetters } from 'vuex'
export default {
	name: 'Tools',
	components: {
		Sign,
	},
	mixins: [isMobile],
	props: {
		enabled: {
			type: Boolean,
			required: true,
		},
		document: {
			type: Object,
			required: true,
			default: () => ({
				name: '',
			}),
		},
		position: {
			type: Object,
			required: true,
			default: () => ({
				top: 0,
				left: 0,
			}),
		},
	},
	data: () => ({
		signSelected: false,
	}),

	computed: {
		...mapGetters({
			getHasPfx: 'getHasPfx',
		}),
	},

	watch: {
		enabledButtons(newVal, oldVal) {
			if (newVal === false) {
				this.signSelected = false
			}
		},
	},

	methods: {
		handleSignPreview(status) {
			this.signSelected = status
		},
		signDocument() {},
	},
}
</script>

<style lang="scss" scoped>
.v-container-tools{
	display: flex;
	flex-direction: column;
	width: auto;
	min-width: 200px;
	position: fixed;
	top: 200px;
	left: 130px;
	background-color: #fff;
	border: 1px solid #e9e9e9;
	border-radius: 10px;
	z-index: 1000;
	text-align: left;
	padding: 10px;

	&::before{
		top: -11px;
		left: 105px;
		border: solid transparent;
		border-bottom-color: #e9e9e9;
		border-top-width: 0;
		content: '';
		display: block;
		position: absolute;
		pointer-events: none;
		z-index: 0;
	}

	&::after{
		top: -10px;
		left: 100px;
		border: solid transparent;
		border-width: 10px;
		content: '';
		display: block;
		position: absolute;
		pointer-events: none;
		z-index: 3;
		border-bottom-color: #fff;
		border-top-width: 0;
	}

	header{
		border-bottom: 1px solid #dedede;
		text-align: center;
		font-size: 1rem;
		font-style: italic;
		font-weight: bold;
		margin-left: 10px;
		margin-right: 10px;
		margin-bottom: 10px;
	}

	.content-actions-tools{
		display: flex;
		flex-direction: row;
		width: 100%;
		justify-content: center;
		align-items: center;
		margin: 10px;

		button{
			&:first-child{
				margin-right: 10px;
			}
		}
	}
}
</style>
