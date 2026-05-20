<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div ref="root" class="markdown-heading-menu">
		<button ref="toggle"
			type="button"
			class="markdown-heading-menu__toggle"
			:aria-expanded="isOpen ? 'true' : 'false'"
			aria-haspopup="menu"
			:aria-label="t('libresign', 'Heading style')"
			@click.stop="toggleMenu"
			@keydown.escape.prevent="closeMenu">
			<NcIconSvgWrapper :path="isOpen ? mdiChevronUp : mdiChevronDown" :size="18" />
			<span>{{ t('libresign', 'H') }}</span>
		</button>

		<ul v-if="isOpen"
			ref="menu"
			class="markdown-heading-menu__menu"
			role="menu"
			:style="menuStyle"
			@keydown.escape.prevent="closeMenu">
			<li role="none">
				<button type="button"
					role="menuitem"
					class="markdown-heading-menu__item"
					@click="clearHeading">
					<span class="markdown-heading-menu__option markdown-heading-menu__option--p">
						{{ t('libresign', 'Paragraph') }}
					</span>
				</button>
			</li>
			<li v-for="level in headingLevels" :key="level" role="none">
				<button type="button"
					role="menuitem"
					class="markdown-heading-menu__item"
					@click="applyHeading(level)">
					<span :class="headingClass(level)">
						<span class="markdown-heading-menu__prefix">H{{ level }} </span>
						<span>{{ headingText(level) }}</span>
					</span>
				</button>
			</li>
		</ul>
	</div>
</template>

<script setup>
import { mdiChevronDown, mdiChevronUp } from '@mdi/js'
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

import { t } from '@nextcloud/l10n'

import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

defineOptions({
	name: 'HeadingMenu',
})

const emit = defineEmits(['clear-heading', 'apply-heading'])

const headingLevels = [1, 2, 3, 4, 5, 6]

const root = ref(null)
const toggle = ref(null)
const menu = ref(null)
const isOpen = ref(false)
const menuStyle = ref({})

/**
 *
 * @param level
 */
function headingClass(level) {
	return ['markdown-heading-menu__option', `markdown-heading-menu__option--${level}`]
}

/**
 *
 * @param level
 */
function headingText(level) {
	return `${t('libresign', 'Heading')} ${level}`
}

/**
 *
 */
function clearHeading() {
	emit('clear-heading')
	closeMenu()
}

/**
 *
 * @param level
 */
function applyHeading(level) {
	emit('apply-heading', level)
	closeMenu()
}

/**
 *
 */
function closeMenu() {
	isOpen.value = false
	menuStyle.value = {}
}

/**
 *
 */
async function toggleMenu() {
	isOpen.value = !isOpen.value
	if (isOpen.value) {
		await nextTick()
		updateMenuPosition()
	}
}

/**
 *
 */
function updateMenuPosition() {
	if (!toggle.value || !menu.value) {
		return
	}

	const toggleRect = toggle.value.getBoundingClientRect()
	const menuRect = menu.value.getBoundingClientRect()
	const viewportWidth = window.innerWidth
	const top = toggleRect.bottom + 6
	const left = Math.min(Math.max(8, toggleRect.left), Math.max(8, viewportWidth - menuRect.width - 8))

	menuStyle.value = {
		position: 'fixed',
		top: `${top}px`,
		left: `${left}px`,
		visibility: 'visible',
	}
}

/**
 *
 * @param event
 */
function onDocumentPointerDown(event) {
	if (!isOpen.value || !root.value) {
		return
	}
	if (!root.value.contains(event.target)) {
		closeMenu()
	}
}

onMounted(() => {
	document.addEventListener('pointerdown', onDocumentPointerDown)
	window.addEventListener('scroll', updateMenuPosition, true)
	window.addEventListener('resize', updateMenuPosition)
})

onBeforeUnmount(() => {
	document.removeEventListener('pointerdown', onDocumentPointerDown)
	window.removeEventListener('scroll', updateMenuPosition, true)
	window.removeEventListener('resize', updateMenuPosition)
})
</script>

<style scoped lang="scss">
.markdown-heading-menu {
	position: relative;
	display: inline-flex;
	flex: 0 0 auto;

	&__toggle {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		height: 32px;
		padding: 0 10px;
		border: 1px solid transparent;
		border-radius: var(--border-radius-element, 10px);
		background: var(--color-background-hover);
		color: var(--color-main-text);
		font-weight: 700;
		cursor: pointer;
	}

	&__toggle:hover,
	&__toggle[aria-expanded='true'] {
		background: var(--color-primary-light);
	}

	&__menu {
		position: fixed;
		top: 0;
		left: 0;
		visibility: hidden;
		z-index: 1200;
		margin: 0;
		padding: 6px 0;
		list-style: none;
		min-width: 170px;
		background: var(--color-main-background);
		border: 1px solid var(--color-border);
		border-radius: 10px;
		box-shadow: 0 10px 26px rgba(15, 23, 42, 0.22);
	}

	&__item {
		display: flex;
		align-items: center;
		width: 100%;
		padding: 6px 12px;
		border: 0;
		background: transparent;
		color: var(--color-main-text);
		text-align: left;
		cursor: pointer;
	}

	&__item:hover {
		background: var(--color-background-hover);
	}

	&__option {
		display: inline-flex;
		align-items: baseline;
		gap: 8px;
		line-height: 1.2;
		width: 100%;
	}

	&__prefix {
		opacity: 0.72;
		font-size: 0.78em;
		font-weight: 700;
		letter-spacing: 0.03em;
	}

	&__option--p {
		font-size: 1rem;
		font-weight: 500;
		line-height: 1.35;
	}

	&__option--1 {
		font-size: 1.5rem;
		font-weight: 800;
		line-height: 1.1;
		letter-spacing: -0.01em;
	}

	&__option--2 {
		font-size: 1.3rem;
		font-weight: 750;
		line-height: 1.15;
		letter-spacing: -0.01em;
	}

	&__option--3 {
		font-size: 1.15rem;
		font-weight: 700;
		line-height: 1.2;
	}

	&__option--4 {
		font-size: 1.03rem;
		font-weight: 650;
		line-height: 1.25;
	}

	&__option--5 {
		font-size: 0.94rem;
		font-weight: 620;
		line-height: 1.3;
	}

	&__option--6 {
		font-size: 0.86rem;
		font-weight: 600;
		line-height: 1.35;
		letter-spacing: 0.01em;
	}
}
</style>
