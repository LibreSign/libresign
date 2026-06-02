<template>
	<div class="card entitlement-card">
		<div class="entitlement-icon-bg">
			<NcIconSvgWrapper :path="mdiCheckDecagramOutline" :size="72" />
		</div>

		<div class="entitlement-content">
			<h2>{{ remaining }}</h2>
			<p class="label">Credits remaining</p>
			<p class="description">Use credits to sign documents instantly</p>
			<div class="entitlement-cta-button">
				<NcButton variant="tertiary" :wide="true">
					Buy Credits
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiCheckDecagramOutline } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'

defineProps<{
	remaining: number
}>()
</script>

<style scoped>
.entitlement-card {
	padding: 20px 22px;
	position: relative;
	overflow: hidden;
	min-height: 184px;
	display: flex;
	align-items: flex-start;
	gap: 14px;

	padding: 18px;
	border-radius: 12px;

	background: linear-gradient(135deg,
			#0F172A 0%,
			#111827 100%);

	box-shadow: 0 8px 20px rgba(15, 23, 42, 0.25);
	color: white;
}

.entitlement-icon-bg {
	position: absolute;
	left: -40px;
	top: 50%;
	transform: translateY(-50%);

	width: 110px;
	height: 110px;

	border-radius: 20px;

	display: flex;
	align-items: center;
	justify-content: center;
	opacity: 0.15;
}

.entitlement-content {
	margin-left: 60px;
	display: flex;
	flex-direction: column;
	gap: 8px;
	--color-main-text: #fff;

	& :deep(.button-vue--primary) {
		margin-top: 10px;
		align-self: flex-start;

		background: #04D56D;
		border: none;
		color: #0F172A;
	}
}

.entitlement-content h2 {
	margin-top: 0;
	font-size: 22px;
	font-weight: 700;
}

.entitlement-content .label {
	font-size: 13px;
	opacity: 0.8;
}

.entitlement-content .description {
	font-size: 12px;
	opacity: 0.6;
}

.entitlement-cta-button {
	&:deep(.button-vue--tertiary) {
		background: #04D56D;
		color: #0F172A;
		font-weight: 600;

		box-shadow: 0 4px 10px rgba(4, 213, 109, 0.25);
	}

	&:deep(.button-vue--tertiary:hover) {
		transform: translateY(-1px);
		box-shadow: 0 6px 14px rgba(4, 213, 109, 0.35);
	}
}
</style>
