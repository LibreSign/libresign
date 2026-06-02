import { toast } from 'vue-sonner'
import { mdiCheckCircle, mdiAlertCircle, mdiInformation } from '@mdi/js'
import { h } from 'vue'
import { NcIconSvgWrapper } from '@nextcloud/vue'

type ToastType = 'success' | 'error' | 'info'

type ToastPosition =
	| 'top-left'
	| 'top-right'
	| 'bottom-left'
	| 'bottom-right'
	| 'bottom-center'

interface NotifyOptions {
	message: string
	type?: ToastType
	position?: ToastPosition
	rich?: boolean
	duration?: number
	icon?: 'success' | 'error' | 'info' | 'warning' | null
	important?: boolean
	dismissible?: boolean
}

function resolveIcon(icon?: NotifyOptions['icon']) {
	let path

	switch (icon) {
		case 'success':
			path = mdiCheckCircle
			break
		case 'error':
			path = mdiAlertCircle
			break
		case 'info':
			path = mdiInformation
			break
		case 'warning':
			path = mdiAlertCircle
			break
		default:
			return undefined
	}

	return h(
		'span',
		{ style: { display: 'inline-flex', marginRight: '12px' } },
		[
			h(NcIconSvgWrapper, {
				path,
				size: 18,
			}),
		]
	)
}

export function notify(options: NotifyOptions) {
	const {
		message,
		type = 'info',
		position,
		duration = 3000,
		icon,
		rich = false,
		important = false,
		dismissible = true,
	} = options
	const overrideDuration = important ? 6000 : duration
	const overrideDismissible = important ? false : dismissible

	toast[type](message, {
		duration: overrideDuration,
		position: position ?? 'top-center',
		icon: resolveIcon(icon),
		richColors: rich,
		dismissible: overrideDismissible,
	})
}

type GenericNotifyOptions = Omit<NotifyOptions, 'type'> & { message: string }

export const notifySuccess = ({ message, dismissible, important, rich = true }: GenericNotifyOptions) =>
	notify({ message, type: 'success', icon: 'success', dismissible, important, rich })

export const notifyError = ({ message, dismissible, important, rich = true }: GenericNotifyOptions) =>
	notify({ message, type: 'error', icon: 'error', dismissible, important, rich })

export const notifyInfo = ({ message, dismissible, important }: GenericNotifyOptions) =>
	notify({ message, type: 'info', icon: 'info', dismissible, important })

export const showError = (message: string, dismissible = false, important = true) => {
	notifyError({ message, dismissible, important })
}

export const showSuccess = (message: string, dismissible = true, important = false) => {
	notifySuccess({ message, dismissible, important })
}

export const showWarning = (message: string, dismissible = true, important = true) => {
	notify({ message, type: 'info', icon: 'warning', dismissible, important, rich: true })
}

export const showInfo = (message: string, dismissible = true, important = false) => {
	notifyInfo({ message, dismissible, important })
}
