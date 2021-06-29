import { loadState } from '@nextcloud/initial-state'

export const geteSettings = () => {
	return JSON.stringify(loadState('libresign', 'config'))
}
