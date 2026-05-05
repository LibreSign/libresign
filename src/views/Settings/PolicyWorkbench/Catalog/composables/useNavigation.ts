/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import type { RealPolicySettingCategory } from '../settings/realTypes'

const BACK_TO_TOP_VISIBLE_AT_PX = 420
const CHIP_STICKY_GAP_PX = 8
const CATEGORY_SWITCH_HYSTERESIS_PX = 28
const CATEGORY_SCROLL_ALIGNMENT_GAP_PX = 12
const SECTION_OBSERVER_BOTTOM_MARGIN_PERCENT = 45
const NAVIGATION_LOCK_MS = 900

export function useNavigation(
	visibleCategorySections: { value: Array<{ key: RealPolicySettingCategory }> },
) {
	const categoryChipsScroller = ref<HTMLElement | null>(null)
	const activeCategory = ref<RealPolicySettingCategory | null>(null)
	const showBackToTop = ref(false)
	const categorySectionElements = new Map<RealPolicySettingCategory, HTMLElement>()
	const scrollSyncRaf = ref<number | null>(null)
	const activeCategorySyncRaf = ref<number | null>(null)
	const navigationLockUntil = ref<number>(0)
	const sectionObserver = ref<IntersectionObserver | null>(null)
	const scrollContainer = ref<Window | HTMLElement>(window)
	const scrollListenerTarget = ref<Window | HTMLElement | null>(null)
	const catalogToolbarRef = ref<HTMLElement | null>(null)

	const visibleCategorySectionsValue = computed(() => visibleCategorySections.value)

	function setCategorySectionRef(category: RealPolicySettingCategory) {
		return (el: unknown) => {
			if (el instanceof HTMLElement) {
				categorySectionElements.set(category, el)
			} else {
				categorySectionElements.delete(category)
			}
		}
	}

	function getPrimaryScrollContainer(): Window | HTMLElement {
		const appContent = document.querySelector('#app-content')
		if (appContent instanceof HTMLElement && appContent.scrollHeight > (appContent.clientHeight + 1)) {
			return appContent
		}

		return resolveScrollContainer()
	}

	function resolveScrollContainer(): Window | HTMLElement {
		const section = categorySectionElements.get(visibleCategorySectionsValue.value[0]?.key ?? 'who-can-sign')
		const start = section ?? categoryChipsScroller.value
		if (!start) {
			return window
		}

		let current: HTMLElement | null = start.parentElement
		while (current) {
			if (isScrollableContainer(current)) {
				return current
			}

			current = current.parentElement
		}

		return window
	}

	function isScrollableContainer(element: HTMLElement): boolean {
		const styles = window.getComputedStyle(element)
		const overflowY = styles.overflowY
		if (overflowY !== 'auto' && overflowY !== 'scroll') {
			return false
		}

		return element.scrollHeight > element.clientHeight
	}

	function categoryActivationLinePx() {
		const stickyContainer = categoryChipsScroller.value?.closest('.policy-workbench__category-nav-sticky') as HTMLElement | null
		if (stickyContainer) {
			return stickyContainer.getBoundingClientRect().bottom + CHIP_STICKY_GAP_PX
		}

		const headerHeight = scrollContainer.value instanceof Window
			? (() => {
				const rootStyles = window.getComputedStyle(document.documentElement)
				const rawHeaderHeight = rootStyles.getPropertyValue('--header-height').trim()
				const parsedHeaderHeight = Number.parseFloat(rawHeaderHeight)
				return Number.isFinite(parsedHeaderHeight) ? parsedHeaderHeight : 50
			})()
			: 0

		const stickyHeight = stickyContainer?.getBoundingClientRect().height ?? 0

		return headerHeight + stickyHeight + CHIP_STICKY_GAP_PX
	}

	function observerTopOffsetPx() {
		const activationLine = categoryActivationLinePx()
		if (scrollContainer.value instanceof Window) {
			return Math.max(0, Math.round(activationLine))
		}

		if (typeof (scrollContainer.value as HTMLElement).getBoundingClientRect !== 'function') {
			return Math.max(0, Math.round(activationLine))
		}

		const containerRect = scrollContainer.value.getBoundingClientRect()
		return Math.max(0, Math.round(activationLine - containerRect.top))
	}

	function pickCategoryByGeometry(): RealPolicySettingCategory {
		const activationLine = categoryActivationLinePx()
		let lastPassedIndex = -1

		for (let i = 0; i < visibleCategorySectionsValue.value.length; i++) {
			const category = visibleCategorySectionsValue.value[i]?.key
			if (!category) continue

			const element = categorySectionElements.get(category)
			if (!element) continue

			const rect = element.getBoundingClientRect()
			if (rect.top + CATEGORY_SWITCH_HYSTERESIS_PX < activationLine) {
				lastPassedIndex = i
			} else {
				break
			}
		}

		if (lastPassedIndex >= 0 && visibleCategorySectionsValue.value[lastPassedIndex]) {
			return visibleCategorySectionsValue.value[lastPassedIndex]!.key
		}

		return visibleCategorySectionsValue.value[0]?.key ?? 'who-can-sign'
	}

	function syncActiveCategory() {
		if (Date.now() < navigationLockUntil.value) {
			return
		}
		activeCategory.value = pickCategoryByGeometry()
	}

	function requestActiveCategorySync() {
		if (activeCategorySyncRaf.value !== null) {
			return
		}

		activeCategorySyncRaf.value = window.requestAnimationFrame(() => {
			activeCategorySyncRaf.value = null
			syncActiveCategory()
		})
	}

	function reconnectSectionObserver() {
		sectionObserver.value?.disconnect()
		sectionObserver.value = null

		const sections = visibleCategorySectionsValue.value
		if (sections.length === 0) {
			activeCategory.value = null
			return
		}

		scrollContainer.value = getPrimaryScrollContainer()
		const topOffset = observerTopOffsetPx()
		const rootHeight = scrollContainer.value instanceof Window
			? window.innerHeight
			: scrollContainer.value.clientHeight
		const bottomOffset = Math.round(rootHeight * (SECTION_OBSERVER_BOTTOM_MARGIN_PERCENT / 100))

		sectionObserver.value = new IntersectionObserver(() => {
			requestActiveCategorySync()
		}, {
			root: scrollContainer.value instanceof Window ? null : scrollContainer.value,
			rootMargin: `-${topOffset}px 0px -${bottomOffset}px 0px`,
			threshold: [0, 0.01, 0.1, 0.25, 0.5, 0.75, 1],
		})

		for (const section of sections) {
			const element = categorySectionElements.get(section.key)
			if (element) {
				sectionObserver.value.observe(element)
			}
		}

		syncActiveCategory()
	}

	function updateBackToTopVisibility() {
		const offset = scrollContainer.value instanceof Window
			? window.scrollY
			: scrollContainer.value.scrollTop

		if (offset <= BACK_TO_TOP_VISIBLE_AT_PX) {
			showBackToTop.value = false
			return
		}

		const toolbar = catalogToolbarRef.value
		if (!toolbar) {
			showBackToTop.value = offset > BACK_TO_TOP_VISIBLE_AT_PX
			return
		}

		if (scrollContainer.value instanceof Window) {
			const toolbarRect = toolbar.getBoundingClientRect()
			const thresholdTop = categoryActivationLinePx() + 4
			showBackToTop.value = toolbarRect.bottom <= thresholdTop
			return
		}

		const toolbarRect = toolbar.getBoundingClientRect()
		if (typeof (scrollContainer.value as HTMLElement).getBoundingClientRect !== 'function') {
			showBackToTop.value = offset > BACK_TO_TOP_VISIBLE_AT_PX
			return
		}
		const containerRect = scrollContainer.value.getBoundingClientRect()
		const toolbarBottomInContainer = toolbarRect.bottom - containerRect.top
		showBackToTop.value = toolbarBottomInContainer <= 4
	}

	function requestCategoryNavigationSync() {
		if (scrollSyncRaf.value !== null) {
			return
		}

		scrollSyncRaf.value = window.requestAnimationFrame(() => {
			scrollSyncRaf.value = null
			updateBackToTopVisibility()
		})
	}

	function scrollElementToViewportOffset(target: HTMLElement) {
		scrollContainer.value = getPrimaryScrollContainer()
		const desiredViewportTop = categoryActivationLinePx() + CATEGORY_SCROLL_ALIGNMENT_GAP_PX
		const targetRect = target.getBoundingClientRect()

		if (scrollContainer.value instanceof Window) {
			const nextTop = Math.max(0, Math.round(window.scrollY + targetRect.top - desiredViewportTop))
			window.scrollTo({
				top: nextTop,
				behavior: 'smooth',
			})
			return
		}

		const nextTop = Math.max(0, Math.round(scrollContainer.value.scrollTop + targetRect.top - desiredViewportTop))
		scrollContainer.value.scrollTo({
			top: nextTop,
			behavior: 'smooth',
		})
	}

	function scrollToCategory(category: RealPolicySettingCategory, event?: MouseEvent) {
		;(event?.currentTarget as HTMLElement | null)?.blur()
		const target = categorySectionElements.get(category) ?? document.getElementById(`policy-category-${category}`)
		if (!target) {
			return
		}

		activeCategory.value = category
		navigationLockUntil.value = Date.now() + NAVIGATION_LOCK_MS
		scrollElementToViewportOffset(target)
	}

	function removeScrollListener() {
		if (!scrollListenerTarget.value) {
			return
		}

		scrollListenerTarget.value.removeEventListener('scroll', requestCategoryNavigationSync)
		scrollListenerTarget.value = null
	}

	function attachScrollListener() {
		removeScrollListener()
		scrollContainer.value = getPrimaryScrollContainer()
		scrollListenerTarget.value = scrollContainer.value
		scrollContainer.value.addEventListener('scroll', requestCategoryNavigationSync, { passive: true })
	}

	// Watch for active category changes to blur focused chip
	watch(activeCategory, () => {
		const focused = document.activeElement as HTMLElement | null
		if (focused?.classList.contains('policy-workbench__category-chip')) {
			focused.blur()
		}
	})

	// Cleanup
	onBeforeUnmount(() => {
		removeScrollListener()
		if (scrollSyncRaf.value !== null) {
			cancelAnimationFrame(scrollSyncRaf.value)
		}
		if (activeCategorySyncRaf.value !== null) {
			cancelAnimationFrame(activeCategorySyncRaf.value)
		}
		sectionObserver.value?.disconnect()
	})

	return {
		// Refs
		categoryChipsScroller,
		activeCategory,
		showBackToTop,
		catalogToolbarRef,
		// Methods
		setCategorySectionRef,
		scrollToCategory,
		reconnectSectionObserver,
		attachScrollListener,
		removeScrollListener,
		updateBackToTopVisibility,
	}
}
