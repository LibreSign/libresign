import { loadState } from '@nextcloud/initial-state'

export const getInitialState = () => {
	try {
		const initialState = loadState('libresign', 'config')
		return initialState
	} catch (err) {
		return console.error('error in loadState: ', err)
	}

}
