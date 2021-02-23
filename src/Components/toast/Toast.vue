<template>
	<div v-if="show" ref="toastAlert" class="toast show">
		<div class="inner">
			<div class="colorBar down" />
			<div class="icon">
				<div id="ToastIcon" class="icon-info-white" />
			</div>
			<div class="toastText">
				<div class="name">
					{{ title }}
				</div>
				<div class="desc">
					{{ content }}
				</div>
			</div>
		</div>
	</div>
</template>
<script>
export default {
	name: 'Toast',
	props: {
		title: {
			type: String,
			required: true,
		},
		content: {
			type: String,
			default: '',
		},
	},
	data() {
		return {
			show: true,
		}
	},
	methods: {
		showToastOn() {
			this.show = true
			this.hideToast()
		},
		hideToast() {
			setTimeout(function() {
				this.show = false
			}.bind(this), 11000)
		},
	},
}
</script>
<style scoped>
.toast{
	display: none;
	background-color: #fff;
	position: absolute;
	top: 0;
	right: 0;
	margin: 10px 10px 0 0;
	border-radius: 6px;
	height: 58px;
	width: 300px;

	user-select: none;
	overflow:hidden;
}

.toast .inner{
	display: flex;
	padding: 5px 3px 5px 3px;
	align-items: center;
	width: 100%;
}

.toast .inner .colorBar{
	background-color: #F0A92E;
	height: 45px;
	width: 6px;
	border-radius: 6px;
	margin-left: 5px;
}

.toast .inner .colorBar.down{
	animation: percent 11s normal;
}

.toast .inner .icon{
	background-color: #F0A92F;
	margin-left: 10px;
	border-radius: 100%;
	width: 25px;
	height: 25px;

	display: flex;
	justify-content: center;
}

.toast .inner .toastText{
	margin-left: 10px;
}

.toast .inner .toastText .name{
	font-weight: bold;
	color: black;
}

.toast .inner .toastText .desc{
	margin-top: -8px;
	color: black;
}

.toast.hidden{
	transition: toastHidde 8s ease forwards;
}

.toast.show{
	display: flex;
	animation: toastShow 1s ease forwards;
}

@keyframes toastShow{
	0%{
		transform: translateX(100%);
	}
	40%{
		transform: translateX(-10%);
	}
	80%{
		transform: translateX(0%);
	}
	100%{
		transform: translateX(-10px)
	}
}
@keyframes percent{
	0%{
		transform: scaleY(1);
	}
	100%{
		transform: scaleY(0);
	}
}
</style>
