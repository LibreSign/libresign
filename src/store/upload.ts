import { ref } from 'vue'
import { getCapabilities } from '@nextcloud/capabilities'
import type { LibresignCapabilities } from '@/types'

import { notifyError } from '@/services/toast'

/**
 * ============================
 * TYPES
 * ============================
 */

/**
 * Unified queue item
 * Supports:
 * - local file uploads
 * - Nextcloud file paths
 */
export type QueuedUploadItem =
  | {
	type: 'file'
	file: File
	status?: 'idle' | 'uploading' | 'success' | 'error'
    progress?: number
    }
  | {
	type: 'path'
	path: string
	name: string
	status?: 'idle' | 'uploading' | 'success' | 'error'
    progress?: number
	}

/**
 * ============================
 * STATE
 * ============================
 */

const pendingItems = ref<QueuedUploadItem[]>([])

/**
 * ============================
 * CONFIG (CACHED)
 * ============================
 */

/**
 * Cache capabilities so we don't repeatedly call getCapabilities()
 * (not expensive, but cleaner + future-proof)
 */
let cachedConfig: ReturnType<typeof getLibresignConfig> | null = null

function getConfig() {
  if (!cachedConfig) {
    cachedConfig = getLibresignConfig()
  }
  return cachedConfig
}

/**
 * Raw config fetch
 */
function getLibresignConfig() {
  const capabilities = getCapabilities() as LibresignCapabilities | undefined
  return capabilities?.libresign?.config ?? null
}

/**
 * Feature flag: envelope support
 */
export function envelopeEnabled(): boolean {
  return getConfig()?.envelope?.['is-available'] === true
}

/**
 * Max file upload limit (fallback = 20)
 */
export function getMaxFileUploads(): number {
  const capabilitiesMax = getConfig()?.upload?.['max-file-uploads']

  return typeof capabilitiesMax === 'number' &&
    Number.isFinite(capabilitiesMax) &&
    capabilitiesMax > 0
    ? Math.floor(capabilitiesMax)
    : 20
}

/**
 * ============================
 * QUEUE OPERATIONS
 * ============================
 */

/**
 * Add items to upload queue
 *
 * IMPORTANT:
 * - This is the SINGLE source of truth for validation
 * - UI must NOT bypass this
 *
 * @param items - items to add
 * @param options.allowMultiple - overrides envelope behavior (UI context)
 */
export function addPendingItems(
  items: QueuedUploadItem[],
  options?: { allowMultiple?: boolean }
) {
  const current = pendingItems.value
  const max = getMaxFileUploads()

  // Determine if multiple files are allowed
  // UI can override, otherwise fallback to system config
  const allowMultiple = options?.allowMultiple ?? envelopeEnabled()

  /**
   *  Block multiple files if not allowed
   */
  if (!allowMultiple && current.length + items.length > 1) {
    notifyError({ message: 'Only one file allowed', important: true })
    return
  }

  /**
   * Enforce max upload limit
   */
  if (current.length + items.length > max) {
    notifyError({ message: `Max ${max} files allowed`, important: true })
    return
  }

  /**
   * Add items to queue
   */
  pendingItems.value.push(...items)
}

/**
 * Get reactive queue
 */
export function getPendingItems() {
  return pendingItems
}

/**
 * Clear queue
 */
export function clearPendingItems() {
  pendingItems.value = []
}

/**
 * Remove item by index
 */
export function removePendingItem(index: number) {
  pendingItems.value.splice(index, 1)
}

/**
 * ============================
 * HELPERS
 * ============================
 */

/**
 * Extract display name from queue item
 */
export function getItemName(item: QueuedUploadItem): string {
  return item.type === 'file'
    ? item.file.name
    : item.name
}

/**
 * Extract display name from queue item
 */
export function getItemSize(item: QueuedUploadItem) {
  if (item.type === 'file') {
    const kb = item.file.size / 1024
    return `${kb.toFixed(1)} KB`
  }

  return 'From files'
}

/**
 * Extract filename from path
 */
export function getItemNameFromPath(path: string): string {
  return path.split('/').pop() || 'file.pdf'
}

/**
 * Convert File[] → QueuedUploadItem[]
 */
export function createQueuedItemsFromFiles(files: File[]): QueuedUploadItem[] {
  return files.map(file => ({
    type: 'file',
    file,
  }))
}

/**
 * Convert string[] paths → QueuedUploadItem[]
 */
export function createQueuedItemsFromPaths(paths: string[]): QueuedUploadItem[] {
  return paths.map(path => ({
    type: 'path',
    path,
    name: getItemNameFromPath(path),
  }))
}
