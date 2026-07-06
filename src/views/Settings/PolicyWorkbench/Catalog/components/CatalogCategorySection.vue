<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section
		:id="category.id"
		:ref="sectionRef"
		class="policy-workbench__category-section"
		:class="{ 'policy-workbench__category-section--active': isActive }"
		:data-category-key="category.key">
		<h3 class="policy-workbench__category-heading">
			<button
				type="button"
				class="policy-workbench__category-toggle"
				:aria-controls="`policy-category-content-${category.key}`"
				:aria-expanded="String(isExpanded)"
				@click="emit('toggle-category', category.key)">
				<NcIconSvgWrapper
					class="policy-workbench__category-toggle-icon"
					:path="isExpanded ? mdiChevronUp : mdiChevronDown"
					:size="18" />
				<span class="policy-workbench__category-title">{{ category.label }}</span>
			</button>
		</h3>
		<div
			:id="`policy-category-content-${category.key}`"
			v-show="isExpanded"
			class="policy-workbench__category-content">
			<div v-if="layout === 'cards'" class="policy-workbench__settings-grid">
				<article
					v-for="summary in category.summaries"
					:key="summary.key"
					class="policy-workbench__setting-tile"
					tabindex="0"
					role="button"
					@pointerdown="handleTrackPress(summary.key, $event)"
					@mouseup="handleSelectionGesture(summary.key)"
					@click="handleOpenFromPointer(summary.key, $event)"
					@keydown.enter.prevent="emit('open-from-keyboard', summary.key)"
					@keydown.space.prevent="emit('open-from-keyboard', summary.key)">
					<div class="policy-workbench__setting-body">
						<div class="policy-workbench__setting-header">
							<div>
								<h3 class="policy-workbench__setting-title">
									<span v-html="highlightText(summary.title)"></span>
									<span v-if="summary.context" class="policy-workbench__setting-context">(<span v-html="highlightText(summary.context)"></span>)</span>
								</h3>
								<p class="policy-workbench__setting-description" v-html="highlightText(summary.description)"></p>
							</div>
						</div>

						<p v-if="hasActiveOverrides(summary.groupCount, summary.userCount, summary.everyoneCount)" class="policy-workbench__origin-badge">
							{{ customRulesActiveLabel }}
						</p>

						<ul class="policy-workbench__setting-stats">
							<li>
								<strong>{{ resolveDefaultStatLabel(summary.key) }}:</strong>
								<span :title="summary.defaultSummary" v-html="highlightText(summary.defaultSummary)"></span>
							</li>
							<li>
								<strong>{{ resolveOverridesStatLabel(summary.key) }}:</strong>
								<span>{{ formatOverrideSummary(summary.groupCount, summary.userCount, summary.key, summary.everyoneCount) }}</span>
							</li>
						</ul>
					</div>

					<div class="policy-workbench__setting-footer">
						<NcButton variant="secondary" class="policy-workbench__manage-button" :aria-label="configureSettingAriaLabel" @click.stop="handleOpenFromAction(summary.key, $event)">
							{{ configureButtonLabel }}
						</NcButton>
					</div>
				</article>
			</div>

			<div v-else class="policy-workbench__settings-list" role="list">
				<article
					v-for="summary in category.summaries"
					:key="summary.key"
					class="policy-workbench__settings-row"
					role="button"
					tabindex="0"
					@pointerdown="handleTrackPress(summary.key, $event)"
					@mouseup="handleSelectionGesture(summary.key)"
					@click="handleOpenFromPointer(summary.key, $event)"
					@keydown.enter.prevent="emit('open-from-keyboard', summary.key)"
					@keydown.space.prevent="emit('open-from-keyboard', summary.key)">
					<div class="policy-workbench__settings-row-main">
						<h3 class="policy-workbench__setting-title">
							<span v-html="highlightText(summary.title)"></span>
							<span v-if="summary.context" class="policy-workbench__setting-context">(<span v-html="highlightText(summary.context)"></span>)</span>
						</h3>
						<p v-html="highlightText(summary.description)"></p>
						<p v-if="hasActiveOverrides(summary.groupCount, summary.userCount, summary.everyoneCount)" class="policy-workbench__origin-badge policy-workbench__origin-badge--inline">
							{{ customRulesActiveLabel }}
						</p>
					</div>

					<div class="policy-workbench__settings-row-stats">
						<span class="policy-workbench__settings-row-stat policy-workbench__settings-row-stat--default" :title="summary.defaultSummary">
							<strong>{{ resolveDefaultStatLabel(summary.key) }}:</strong>
							<span v-html="highlightText(summary.defaultSummary)"></span>
						</span>
						<span class="policy-workbench__settings-row-stat policy-workbench__settings-row-stat--count"><strong>{{ resolveOverridesStatLabel(summary.key) }}:</strong> {{ formatOverrideSummary(summary.groupCount, summary.userCount, summary.key, summary.everyoneCount) }}</span>
					</div>

					<NcButton variant="secondary" class="policy-workbench__manage-button" :aria-label="configureSettingAriaLabel" @click.stop="handleOpenFromAction(summary.key, $event)">
						{{ configureButtonLabel }}
					</NcButton>
				</article>
			</div>
		</div>
	</section>
</template>

<script setup lang="ts">
import { mdiChevronDown, mdiChevronUp } from '@mdi/js'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import type { VNodeRef } from 'vue'

import type {
	CatalogCategorySection as CatalogCategorySectionModel,
	CatalogLayout,
} from '../composables/useCatalogPresentation'
import type { RealPolicySettingCategory } from '../../settings/realTypes'

defineOptions({
	name: 'CatalogCategorySection',
})

const emit = defineEmits<{
	'toggle-category': [category: RealPolicySettingCategory]
	'track-press': [payload: { layout: CatalogLayout, key: string, event: PointerEvent }]
	'mark-selection': [payload: { layout: CatalogLayout, key: string }]
	'open-from-pointer': [payload: { layout: CatalogLayout, key: string, event: MouseEvent }]
	'open-from-keyboard': [key: string]
	'open-from-action': [payload: { key: string, event: MouseEvent }]
}>()

const props = defineProps<{
	category: CatalogCategorySectionModel
	layout: CatalogLayout
	isActive: boolean
	isExpanded: boolean
	sectionRef?: VNodeRef | null
	highlightText: (value: string) => string
	hasActiveOverrides: (groupCount?: number, userCount?: number, everyoneCount?: number) => boolean
	resolveDefaultStatLabel: (policyKey: string) => string
	resolveOverridesStatLabel: (policyKey: string) => string
	formatOverrideSummary: (groupCount?: number, userCount?: number, policyKey?: string, everyoneCount?: number) => string
}>()

// TRANSLATORS Badge text indicating a setting has one or more custom policy overrides.
const customRulesActiveLabel = t('libresign', 'Custom rules active')
// TRANSLATORS Aria label for the action button that opens configuration for the selected policy setting.
const configureSettingAriaLabel = t('libresign', 'Configure setting')
// TRANSLATORS Action button label that opens the editor for the selected policy setting.
const configureButtonLabel = t('libresign', 'Configure')

function handleTrackPress(key: string, event: PointerEvent) {
	emit('track-press', {
		layout: props.layout,
		key,
		event,
	})
}

function handleSelectionGesture(key: string) {
	emit('mark-selection', {
		layout: props.layout,
		key,
	})
}

function handleOpenFromPointer(key: string, event: MouseEvent) {
	emit('open-from-pointer', {
		layout: props.layout,
		key,
		event,
	})
}

function handleOpenFromAction(key: string, event: MouseEvent) {
	emit('open-from-action', { key, event })
}
</script>

<style scoped lang="scss">
.policy-workbench {
	&__category-heading {
		margin: 0;
		display: block;
	}

	&__category-toggle {
		display: flex;
		width: 100%;
		align-items: center;
		gap: 0.45rem;
		background: none;
		border: none;
		padding: 0.2rem 0;
		margin: 0;
		cursor: pointer;
		text-align: start;
		color: inherit;

		&:focus-visible {
			outline: 2px solid color-mix(in srgb, var(--color-primary-element) 62%, white 38%);
			outline-offset: 3px;
			border-radius: 6px;
		}
	}

	&__category-toggle-icon {
		color: var(--color-text-maxcontrast);
	}

	&__category-content {
		margin-top: 0.8rem;
	}

	&__category-section {
		scroll-margin-top: 5rem;
		padding-top: 1rem;
		border-top: 1px solid color-mix(in srgb, var(--color-border-maxcontrast) 26%, transparent);

		&:first-child {
			padding-top: 0;
			border-top: none;
		}

		&--active {
			transition: background-color 0.2s ease;
		}
	}

	&__category-title {
		margin: 0;
		font-size: 1.08rem;
		font-weight: 800;
		line-height: 1.3;
		letter-spacing: 0.005em;
		text-transform: none;
		color: color-mix(in srgb, var(--color-main-text) 88%, var(--color-text-maxcontrast));
	}

	&__settings-grid {
		margin-top: 1rem;
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(min(320px, 100%), 1fr));
		gap: 1rem;
		align-items: stretch;
	}

	&__settings-list {
		margin-top: 1rem;
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}

	&__settings-row {
		display: grid;
		grid-template-columns: minmax(220px, 1.2fr) minmax(0, 1fr) auto;
		gap: 1rem;
		align-items: center;
		padding: 0.9rem 1rem;
		border-radius: 14px;
		border: 1px solid color-mix(in srgb, var(--color-primary-element) 12%, var(--color-border-maxcontrast));
		background: color-mix(in srgb, var(--color-main-background) 94%, white);
		transition: border-color 0.15s ease, box-shadow 0.15s ease;

		&:hover,
		&:focus-within {
			border-color: color-mix(in srgb, var(--color-primary-element) 40%, var(--color-border-maxcontrast));
			box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary-element) 12%, transparent);
		}

		:deep(.button-vue) {
			flex-shrink: 0;
		}
	}

	&__settings-row-main {
		min-width: 0;

		h3,
		p {
			margin: 0;
		}

		h3 {
			overflow-wrap: break-word;
			word-break: normal;
			hyphens: auto;
		}

		p:not(.policy-workbench__origin-badge) {
			margin-top: 0.25rem;
			color: var(--color-text-maxcontrast);
			line-height: 1.4;
			overflow-wrap: break-word;
			word-break: normal;
			hyphens: auto;
		}
	}

	&__origin-badge {
		margin: 0;
		display: inline-flex;
		align-self: flex-start;
		align-items: center;
		padding: 0.2rem 0.55rem;
		border-radius: 999px;
		font-size: 0.76rem;
		font-weight: 600;
		line-height: 1.25;
		color: var(--color-primary-element);
		border: 1px solid color-mix(in srgb, var(--color-primary-element) 28%, var(--color-border-maxcontrast));
		background: color-mix(in srgb, var(--color-primary-element) 16%, var(--color-main-background));

		&--inline {
			margin-top: 0.45rem;
		}
	}

	&__settings-row-stats {
		display: flex;
		flex-wrap: wrap;
		gap: 0.55rem 0.8rem;
		align-items: baseline;
		font-size: 0.9rem;
		color: var(--color-text-maxcontrast);
		min-width: 0;
	}

	&__settings-row-stat {
		min-width: 0;
		white-space: normal;
		overflow-wrap: break-word;

		&--default {
			display: flex;
			align-items: baseline;
			gap: 0.25rem;
			flex: 1 1 260px;
			min-width: 0;

			strong {
				white-space: nowrap;
				flex-shrink: 0;
			}

			span {
				display: block;
				min-width: 0;
				white-space: normal;
				overflow-wrap: break-word;
				word-break: normal;
				hyphens: auto;
			}
		}

		&--count {
			white-space: nowrap;
			flex: 0 0 auto;
		}
	}

	&__setting-tile {
		text-align: left;
		padding: 1.25rem;
		border-radius: 20px;
		border: 1px solid color-mix(in srgb, var(--color-primary-element) 14%, var(--color-border-maxcontrast));
		background:
			radial-gradient(circle at top right, color-mix(in srgb, var(--color-primary-element) 14%, transparent), transparent 45%),
			linear-gradient(180deg, color-mix(in srgb, var(--color-main-background) 92%, white), var(--color-main-background));
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		gap: 1rem;
		min-height: 240px;
		cursor: pointer;
		transition: border-color 0.15s ease, box-shadow 0.15s ease;

		&:hover,
		&:focus-visible,
		&:focus-within {
			border-color: color-mix(in srgb, var(--color-primary-element) 46%, var(--color-border-maxcontrast));
			box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary-element) 14%, transparent);
		}

		h3,
		p {
			margin: 0;
		}

		:deep(.button-vue) {
			flex-shrink: 0;
		}
	}

	&__setting-header {
		display: block;

		> div {
			min-width: 0;
		}

		h3 {
			overflow-wrap: break-word;
			word-break: normal;
			hyphens: auto;
		}
	}

	&__setting-title {
		display: inline-flex;
		align-items: baseline;
		gap: 0.35rem;
	}

	&__setting-context {
		font-size: 0.88em;
		font-weight: 500;
		color: var(--color-text-maxcontrast);
	}

	&__setting-body {
		display: flex;
		flex-direction: column;
		gap: 0.85rem;
		min-height: 0;
	}

	&__setting-description {
		margin: 0;
		color: var(--color-text-maxcontrast);
		line-height: 1.4;
		min-height: calc(1.4em * 2);
		overflow-wrap: break-word;
		word-break: normal;
		hyphens: auto;
	}

	&__setting-footer {
		margin-top: auto;
		display: flex;
		justify-content: flex-start;
	}

	&__setting-stats {
		margin: 0;
		padding: 0;
		list-style: none;
		display: flex;
		flex-direction: column;
		gap: 0.5rem;

		li {
			display: flex;
			gap: 0.3rem;
			align-items: baseline;
			min-width: 0;

			strong {
				white-space: nowrap;
				flex-shrink: 0;
			}

			span {
				min-width: 0;
				overflow-wrap: anywhere;
			}
		}
	}

	&__manage-button {
		:deep(.button-vue) {
			transition: transform 0.12s ease;
		}
	}

	&__setting-tile:hover &__manage-button,
	&__setting-tile:focus-within &__manage-button,
	&__settings-row:hover &__manage-button,
	&__settings-row:focus-within &__manage-button {
		:deep(.button-vue) {
			transform: translateY(-1px);
		}
	}

	:deep(mark) {
		background: color-mix(in srgb, var(--color-warning) 35%, transparent);
		color: inherit;
		padding: 0 0.1rem;
		border-radius: 3px;
	}
}

@media (max-width: 960px) {
	.policy-workbench {
		&__category-title {
			font-size: 1rem;
		}

		&__settings-row {
			grid-template-columns: minmax(0, 1fr);
			align-items: stretch;

			:deep(.button-vue) {
				width: 100%;
				justify-content: center;
			}
		}
	}
}

@media (max-width: 640px) {
	.policy-workbench {
		&__settings-grid {
			gap: 0.75rem;
		}

		&__setting-tile {
			padding: 1rem;
		}

		&__setting-stats {
			gap: 0.5rem;

			li {
				word-break: break-word;
			}
		}
	}
}
</style>
