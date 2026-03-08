/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed } from '@vue/reactivity'

import { useSidebarStore } from '../store/sidebar.js'

type FileEntryMetadata = {
	extension?: string
}

type FileEntrySigner = {
	displayName?: string
	email?: string
	me?: boolean
	sign_uuid?: string
}

export type FileEntrySource = {
	id: number
	name: string
	basename?: string
	uuid?: string
	nodeType?: string
	created_at?: number | string | Date | null
	metadata?: FileEntryMetadata
	status?: number
	statusText?: string
	signers?: FileEntrySigner[]
	signersCount?: number
	nodeId?: number
	file?: string
	[key: string]: unknown
}

type FileEntryStore = {
	selectFile: (id: number) => void
}

type ActionsMenuStore = {
	opened: number | null
}

type FileEntryProps = {
	source: FileEntrySource
}

export function useFileEntry(
	props: FileEntryProps,
	options: {
		actionsMenuStore: ActionsMenuStore
		filesStore: FileEntryStore
	},
) {
	const sidebarStore = useSidebarStore()

	const mtime = computed(() => new Date(props.source?.created_at || Date.now()))
	const openedMenu = computed({
		get: () => options.actionsMenuStore.opened === props.source.id,
		set: (opened: boolean) => {
			options.actionsMenuStore.opened = opened ? props.source.id : null
		},
	})
	const mtimeOpacity = computed(() => {
		const maxOpacityTime = 31 * 24 * 60 * 60 * 1000
		const timestamp = mtime.value?.getTime?.()

		if (!timestamp) {
			return {}
		}

		const ratio = Math.round(Math.min(100, 100 * (maxOpacityTime - (Date.now() - timestamp)) / maxOpacityTime))
		if (ratio < 0) {
			return {}
		}

		return {
			color: `color-mix(in srgb, var(--color-main-text) ${ratio}%, var(--color-text-maxcontrast))`,
		}
	})
	const fileExtension = computed(() => {
		if (props.source.nodeType === 'envelope') {
			return ''
		}
		return props.source.metadata?.extension ? `.${props.source.metadata.extension}` : '.pdf'
	})

	function onRightClick(event: MouseEvent) {
		if (openedMenu.value) {
			return
		}

		options.actionsMenuStore.opened = props.source.id
		event.preventDefault()
		event.stopPropagation()

		const target = event.currentTarget as HTMLElement | null
		const root = target?.closest('.app-content') as HTMLElement | null
		if (!root) {
			return
		}

		const contentRect = root.getBoundingClientRect()
		root.style.setProperty('--mouse-pos-x', `${Math.max(0, event.clientX - contentRect.left - 200)}px`)
		root.style.setProperty('--mouse-pos-y', `${Math.max(0, event.clientY - contentRect.top)}px`)
	}

	function openDetailsIfAvailable(event: Event) {
		event.preventDefault()
		event.stopPropagation()
		options.filesStore.selectFile(props.source.id)
		sidebarStore.activeRequestSignatureTab()
	}

	return {
		mtime,
		openedMenu,
		mtimeOpacity,
		fileExtension,
		onRightClick,
		openDetailsIfAvailable,
	}
}
