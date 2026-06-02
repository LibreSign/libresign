<template>
	<div class="dashboard">

		<!-- ===================== -->
		<!-- PAGE HEADER -->
		<!-- ===================== -->

		<div class="page-header">

			<div class="header-content">
				<h1>Workflow Dashboard</h1>

				<p>
					Manage signatures request workflows from one operational workspace.
				</p>
			</div>

		</div>

		<!-- ===================== -->
		<!-- LOADING -->
		<!-- ===================== -->

		<div v-if="dashboard.loading" class="dashboard-loading">

			<!-- HERO -->
			<div class="skeleton-hero" />

			<!-- STATS -->
			<div class="stats-skeleton-grid">
				<div
					v-for="i in 3"
					:key="i"
					class="skeleton-card"
				/>
			</div>

			<!-- TABLE -->
			<div class="table-skeleton">
				<div
					v-for="i in 5"
					:key="i"
					class="skeleton-row"
				/>
			</div>

		</div>

		<!-- ===================== -->
		<!-- ERROR -->
		<!-- ===================== -->

		<div v-else-if="dashboard.error" class="empty-state">
			<h3>Something went wrong</h3>

			<p>{{ dashboard.error }}</p>
		</div>

		<!-- ===================== -->
		<!-- CONTENT -->
		<!-- ===================== -->

		<div v-else class="dashboard-content">

			<!-- ===================== -->
			<!-- HERO + SIDE -->
			<!-- ===================== -->

			<div class="top-grid">

				<!-- LEFT -->
				<div class="hero-column">
					<UploadHero />
				</div>

				<!-- RIGHT -->
				<div class="side-column">

					<StatsCards :stats="dashboard.stats" />

					<EntitlementCard
						:remaining="dashboard.entitlements?.SIGN_DOCUMENT?.remainingUses || 0"
					/>

				</div>

			</div>

			<!-- ===================== -->
			<!-- ACTION REQUIRED -->
			<!-- ===================== -->

			<section class="dashboard-section">
				<ActionableRequiredTable
					:items="dashboard.actionableDocuments"
				/>
			</section>

			<!-- ===================== -->
			<!-- MY DOCUMENTS -->
			<!-- ===================== -->

			<section class="dashboard-section">

				<MyDocumentsTable
					:items="dashboard.myDocuments"
				/>

			</section>

		</div>

	</div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue'

import { useDashboardStore } from '@/store/dashboard'
import { useFilesStore } from '@/store/files'

import UploadHero from './components/UploadHero.vue'
import StatsCards from './components/StatsCards.vue'
import EntitlementCard from './components/EntitlementCard.vue'

import ActionableRequiredTable from './components/ActionableRequiredTable.vue'
import MyDocumentsTable from './components/MyDocumentsTable.vue'

const dashboard = useDashboardStore()
const filesStore = useFilesStore()

onMounted(async () => {
	await dashboard.loadDashboard()

	console.log('[Dashboard]', {
		actionable: dashboard.actionableDocuments,
		myDocuments: dashboard.myDocuments,
		stats: dashboard.stats,
	})
})

onBeforeUnmount(() => {
	filesStore.selectFile()
})
</script>

<style lang="scss" scoped>
.dashboard {
	margin-top: 30px;
	padding: 28px;

	border-radius: 24px;

	background:
		linear-gradient(
			to bottom,
			rgba(4, 213, 109, 0.02),
			transparent 280px
		);

	min-height: 100%;
}

/* =======================
   HEADER
======================= */

.page-header {
	margin-bottom: 32px;
}

.header-content h1 {
	margin: 0;

	font-size: 32px;
	font-weight: 720;
	letter-spacing: -0.8px;

	color: var(--color-main-text);
}

.header-content p {
	margin: 10px 0 0;

	max-width: 620px;

	font-size: 15px;
	line-height: 1.7;

	color: var(--color-text-maxcontrast);
}

/* =======================
   CONTENT
======================= */

.dashboard-content {
	display: flex;
	flex-direction: column;
	gap: 28px;
}

/* =======================
   TOP GRID
======================= */

.top-grid {
	display: grid;
	grid-template-columns: minmax(0, 2fr) minmax(300px, 1fr);

	gap: 24px;
	align-items: flex-start;
}

.hero-column {
	min-width: 0;
}

.side-column {
	display: flex;
	flex-direction: column;
	gap: 18px;
}

/* =======================
   SECTIONS
======================= */

.dashboard-section {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

/* =======================
   LOADING
======================= */

.dashboard-loading {
	display: flex;
	flex-direction: column;
	gap: 24px;
}

.stats-skeleton-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 18px;
}

.skeleton-card {
	height: 110px;

	border-radius: 18px;

	background:
		linear-gradient(
			90deg,
			rgba(15, 23, 42, 0.04) 25%,
			rgba(15, 23, 42, 0.07) 50%,
			rgba(15, 23, 42, 0.04) 75%
		);

	background-size: 200% 100%;

	animation: shimmer 1.4s infinite linear;
}

.skeleton-hero {
	height: 220px;

	border-radius: 22px;

	background:
		linear-gradient(
			90deg,
			rgba(15, 23, 42, 0.04) 25%,
			rgba(15, 23, 42, 0.07) 50%,
			rgba(15, 23, 42, 0.04) 75%
		);

	background-size: 200% 100%;

	animation: shimmer 1.4s infinite linear;
}

.table-skeleton {
	padding: 20px;

	border-radius: 22px;

	background: white;

	border: 1px solid rgba(15, 23, 42, 0.06);

	box-shadow:
		0 1px 2px rgba(15, 23, 42, 0.04),
		0 12px 32px rgba(15, 23, 42, 0.04);
}

.skeleton-row {
	height: 72px;

	margin-bottom: 14px;

	border-radius: 16px;

	background:
		linear-gradient(
			90deg,
			rgba(15, 23, 42, 0.04) 25%,
			rgba(15, 23, 42, 0.07) 50%,
			rgba(15, 23, 42, 0.04) 75%
		);

	background-size: 200% 100%;

	animation: shimmer 1.4s infinite linear;
}

.skeleton-row:last-child {
	margin-bottom: 0;
}

/* =======================
   EMPTY
======================= */

.empty-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;

	padding: 80px 20px;

	text-align: center;
}

.empty-state h3 {
	margin: 0;

	font-size: 22px;
	font-weight: 700;
}

.empty-state p {
	margin-top: 10px;

	font-size: 14px;
	color: var(--color-text-maxcontrast);
}

/* =======================
   ANIMATIONS
======================= */

@keyframes shimmer {
	0% {
		background-position: 200% 0;
	}

	100% {
		background-position: -200% 0;
	}
}

/* =======================
   RESPONSIVE
======================= */

@media (max-width: 1024px) {
	.top-grid {
		grid-template-columns: 1fr;
	}

	.stats-skeleton-grid {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 768px) {
	.dashboard {
		padding: 18px;
	}

	.header-content h1 {
		font-size: 28px;
	}

	.header-content p {
		font-size: 14px;
	}
}
</style>
