<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<article
		class="policy-rule-card"
		:class="{ 'policy-rule-card--highlighted': highlighted, 'policy-rule-card--editable': showEditAction }"
		:aria-label="cardAriaLabel || `${eyebrow}: ${title}`"
		@pointerdown="trackPress"
		@mouseup="markSelectionGesture"
		@click="handleCardClick">
		<div class="policy-rule-card__header">
			<div>
				<p class="policy-rule-card__eyebrow">{{ eyebrow }}</p>
				<h4>{{ title }}</h4>
			</div>
			<span class="policy-rule-card__summary" :title="summary">{{ summary }}</span>
		</div>

		<p v-if="description" class="policy-rule-card__description">
			{{ description }}
		</p>

		<ul v-if="badges.length > 0" class="policy-rule-card__badges">
			<li v-for="badge in badges" :key="badge">
				{{ badge }}
			</li>
		</ul>

		<div class="policy-rule-card__actions">
			<NcButton v-if="showEditAction" variant="tertiary" class="policy-rule-card__action policy-rule-card__action--edit" :aria-label="editLabel" @click.stop="$emit('edit')">
				{{ editText || editLabel }}
			</NcButton>
			<NcButton v-if="showRemoveAction" variant="error" class="policy-rule-card__action policy-rule-card__action--remove" :aria-label="removeLabel" @click.stop="$emit('remove')">
				{{ removeText || removeLabel }}
			</NcButton>
		</div>
	</article>
</template>

<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import { ref } from 'vue'

const DRAG_EDIT_THRESHOLD_PX = 6
const SELECTION_GUARD_WINDOW_MS = 250

defineOptions({
	name: 'PolicyRuleCard',
})

const emit = defineEmits<{
	edit: []
	remove: []
}>()

const props = withDefaults(defineProps<{
	eyebrow: string
	title: string
	summary: string
	description?: string
	badges?: string[]
	highlighted?: boolean
	cardAriaLabel?: string
	editLabel: string
	removeLabel: string
	editText?: string
	removeText?: string
	showEditAction?: boolean
	showRemoveAction?: boolean
}>(), {
	description: '',
	badges: () => [],
	highlighted: false,
	cardAriaLabel: '',
	editText: '',
	removeText: '',
	showEditAction: true,
	showRemoveAction: true,
})

const lastPress = ref<{ x: number, y: number } | null>(null)
const recentSelectionAt = ref(0)

function hasActiveTextSelection() {
	const selection = window.getSelection()
	return !!selection && selection.type === 'Range' && selection.toString().trim().length > 0
}

function markSelectionGesture() {
	if (hasActiveTextSelection()) {
		recentSelectionAt.value = Date.now()
	}
}

function trackPress(event: PointerEvent) {
	if (event.button !== 0) {
		lastPress.value = null
		return
	}

	lastPress.value = {
		x: event.clientX,
		y: event.clientY,
	}
}

function movedBeyondThreshold(event: MouseEvent, press: { x: number, y: number }) {
	const deltaX = Math.abs(event.clientX - press.x)
	const deltaY = Math.abs(event.clientY - press.y)
	return deltaX > DRAG_EDIT_THRESHOLD_PX || deltaY > DRAG_EDIT_THRESHOLD_PX
}

function shouldIgnoreDueToRecentSelection() {
	return (Date.now() - recentSelectionAt.value) <= SELECTION_GUARD_WINDOW_MS
}

function handleCardClick(event: MouseEvent) {
	if (!props.showEditAction) {
		return
	}

	const target = event.target as HTMLElement | null
	if (target?.closest('button, a, input, textarea, select, [role="button"]')) {
		return
	}

	if (hasActiveTextSelection() || shouldIgnoreDueToRecentSelection()) {
		return
	}

	const press = lastPress.value
	if (press && movedBeyondThreshold(event, press)) {
		return
	}

	emit('edit')
}
</script>

<style scoped lang="scss">
.policy-rule-card {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
	padding: 1rem;
	border-radius: 18px;
	border: 1px solid color-mix(in srgb, var(--color-border-maxcontrast) 82%, transparent);
	background: var(--color-main-background);

	&--editable {
		cursor: pointer;
	}

	&--highlighted {
		border-color: var(--color-primary-element);
		box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary-element) 18%, transparent);
	}

	&__header {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		gap: 1rem;

		h4 {
			margin: 0;
		}
	}

	&__eyebrow {
		margin: 0 0 0.25rem;
		font-size: 0.72rem;
		text-transform: uppercase;
		letter-spacing: 0.04em;
		color: var(--color-text-maxcontrast);
	}

	&__summary {
		font-weight: 700;
		max-width: min(64%, 26rem);
		text-align: right;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	&__description {
		margin: 0;
		color: var(--color-text-maxcontrast);
		word-break: break-word;
	}

	&__badges {
		margin: 0;
		padding: 0;
		list-style: none;
		display: flex;
		flex-wrap: wrap;
		gap: 0.5rem;

		li {
			padding: 0.25rem 0.6rem;
			border-radius: 999px;
			background: color-mix(in srgb, var(--color-primary-element) 12%, var(--color-main-background));
			font-size: 0.8rem;
		}
	}

	&__actions {
		display: flex;
		flex-wrap: wrap;
		gap: 0.75rem;
		align-items: center;
		margin-top: 0.25rem;
	}

	&__action {
		min-width: 0;
		font-weight: 600;
	}

	&__action--remove {
		margin-left: auto;
	}
}

@media (max-width: 640px) {
	.policy-rule-card {
		padding: 0.85rem;

		&__header {
			flex-direction: column;
			align-items: flex-start;
		}

		&__summary {
			max-width: 100%;
			text-align: left;
		}

		&__actions {
			:deep(.button-vue) {
				width: 100%;
				justify-content: center;
			}
		}
	}
}
</style>
