import { notifyInfo } from '@/services/toast'
import { ref, onMounted, onUnmounted } from 'vue'

/**
 * Global reactive network state
 *
 * IMPORTANT:
 * - Shared across payment/recovery flows
 * - Avoids duplicated online/offline listeners
 * - Keeps connectivity concerns outside payment orchestration
 */
const isOffline = ref(
	typeof navigator !== 'undefined'
		? !navigator.onLine
		: false
)

/**
 * Listener registry
 *
 * Allows composables/features to subscribe to:
 * - reconnect events
 * - visibility restoration
 *
 * without tightly coupling to DOM events.
 */
const reconnectListeners = new Set<() => void>()

/**
 * Prevent duplicate global listeners
 */
let initialised = false

/**
 * Global handlers
 */
let onlineHandler: (() => void) | null = null
let offlineHandler: (() => void) | null = null
let visibilityHandler: (() => void) | null = null
let lastConnectivityToastAt = 0


function canShowConnectivityToast() {
	const now = Date.now()

	if (now - lastConnectivityToastAt < 4000) {
		return false
	}

	lastConnectivityToastAt = now

	return true
}

/**
 * Mark offline
 */
function markOffline() {
	isOffline.value = true
}

/**
 * Mark online
 */
function markOnline() {
	isOffline.value = false
}

/**
 * Network error classifier
 *
 * IMPORTANT:
 * Axios/network requests:
 * - no response + request exists
 * - timeout
 */
function isNetworkError(error: any): boolean {
	return (
		(!error?.response && !!error?.request) ||
		error?.code === 'ECONNABORTED'
	)
}

/**
 * Register reconnect callback
 *
 * Returns cleanup function.
 */
function onReconnect(callback: () => void) {
	reconnectListeners.add(callback)

	return () => {
		reconnectListeners.delete(callback)
	}
}

/**
 * Notify reconnect listeners
 */
function notifyReconnectListeners() {
	for (const callback of reconnectListeners) {
		try {
			callback()
		} catch (err) {
			console.warn(
				'[NetworkState] reconnect listener failed',
				err,
			)
		}
	}
}

/**
 * Start global listeners once
 */
function initialise() {
	if (typeof window === 'undefined') {
		return
    }

	if (initialised) {
		return
	}

	initialised = true

	onlineHandler = () => {
		if (!isOffline.value) return

		markOnline()

		console.log(
			'[NetworkState] back online'
		)

		if (canShowConnectivityToast()) {
			notifyInfo({
				message:
					'Connection restored! Syncing...',
			})
	    }
		notifyReconnectListeners()
	}

	offlineHandler = () => {
		if (isOffline.value) return

		markOffline()

		console.warn(
			'[NetworkState] offline'
		)

		if (canShowConnectivityToast()) {
			notifyInfo({
				message:
					`Connection lost`,
			})
	    }
	}

	/**
	 * Browser tabs throttle intervals/timers heavily
	 * when hidden.
	 *
	 * Trigger reconnect-style recovery when tab
	 * becomes visible again.
	 */
	visibilityHandler = () => {

		if (document.visibilityState !== 'visible') {
			return
		}

		console.log(
			'[NetworkState] tab visible'
		)

		notifyReconnectListeners()
	}

	window.addEventListener(
		'online',
		onlineHandler,
	)

	window.addEventListener(
		'offline',
		offlineHandler,
	)

	document.addEventListener(
		'visibilitychange',
		visibilityHandler,
	)
}

/**
 * Cleanup global listeners
 *
 * Mostly useful for tests/HMR.
 */
function destroy() {

	if (!initialised) {
		return
	}

	if (onlineHandler) {
		window.removeEventListener(
			'online',
			onlineHandler,
		)
	}

	if (offlineHandler) {
		window.removeEventListener(
			'offline',
			offlineHandler,
		)
	}

	if (visibilityHandler) {
		document.removeEventListener(
			'visibilitychange',
			visibilityHandler,
		)
	}

	reconnectListeners.clear()

	onlineHandler = null
	offlineHandler = null
	visibilityHandler = null

	initialised = false
}

/**
 * Public composable
 */
export function useNetworkState() {

	onMounted(() => {
		initialise()
	})

	onUnmounted(() => {
		/**
		 * Intentionally NO destroy().
		 *
		 * Why:
		 * - Network state is app-global
		 * - Multiple composables may consume it
		 * - Destroying on unmount could break
		 *   active payment recovery flows
		 */
	})

	return {
		isOffline,
		markOffline,
		markOnline,
		isNetworkError,
		onReconnect,
		initialise,
	}
}
