<template>
	<div class="stats">
		<div class="card stat-card">
			<div class="left">
				<div class="icon">
					<NcIconSvgWrapper :path="mdiFileDocumentOutline" :size="20" />
				</div>
			</div>

			<div class="right">
				<h2>{{ stats.totalDocuments }}</h2>
				<p>Total</p>
			</div>
		</div>

		<div class="card stat-card">

			<div class="left">
				<div class="icon pending">
					<NcIconSvgWrapper :path="mdiClockOutline" :size="20" />
				</div>
			</div>

			<div class="right">
				<h2>{{ stats.pendingDocuments }}</h2>
				<p>Pending</p>
			</div>
		</div>

		<div class="card stat-card">
			<div class="left">
				<div class="icon success">
					<NcIconSvgWrapper :path="mdiCheckCircleOutline" :size="20" />
				</div>
			</div>

			<div class="right">
				<h2>{{ stats.completedDocuments }}</h2>
				<p>Completed</p>
			</div>
		</div>

		<div class="card stat-card">
			<div class="left">
				<div class="icon draft">
					<NcIconSvgWrapper :path="mdiFileEditOutline" :size="20" />
				</div>
			</div>

			<div class="right">
				<h2>{{ stats.draftDocuments }}</h2>
				<p>Drafts</p>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {
	mdiFileDocumentOutline,
	mdiClockOutline,
	mdiCheckCircleOutline,
	mdiFileEditOutline
} from '@mdi/js'
defineProps<{
	stats: {
		totalDocuments: number
		pendingDocuments: number
		completedDocuments: number
		draftDocuments: number
	}
}>()
</script>

<style scoped>
.stats {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	align-content: flex-start;
}

.stat-card {
	flex: 1 1 calc(50% - 6px);
	min-width: 120px;
	position: relative;
	padding: 16px;
	background: white;
	border-radius: 12px;
	border: 1px solid var(--color-border);
	padding: 16px;

	display: flex;
	align-items: center;
	gap: 14px;

	min-height: 96px;

	.left {
		flex-shrink: 0;
	}

	.icon {
		width: 40px;
		height: 40px;

		display: flex;
		align-items: center;
		justify-content: center;

		border-radius: 10px;
		background: rgba(4, 213, 109, 0.12);
	}

	/* RIGHT CONTENT */
	.right {
		display: flex;
		flex-direction: column;
		min-width: 0;
	}

	.right h2 {
		font-size: 20px;
		font-weight: 700;
		margin: 0;

		white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
	}

	.right p {
		font-size: 13px;
		color: var(--color-text-maxcontrast);
		margin: 0;

		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.left {
		flex-shrink: 0;
	}
}

.stat-card:hover {
	transform: translateY(-2px);
	box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
}

.icon.pending {
	background: rgba(255, 165, 0, 0.1);
	color: rgb(255, 165, 0);
}

.icon.success {
	background: rgba(0, 200, 100, 0.1);
	color: var(--gp-success-text);
}

.icon.draft {
	background: rgba(120, 120, 120, 0.1);
	color: rgb(120, 120, 120);
}
</style>
