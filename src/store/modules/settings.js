import { loadState } from '@nextcloud/initial-state'

const libresignState = loadState('libresign', 'config', {})

export default {
	namespaced: true,

	state: {
		...{
			hasSignatureFile: false,
			identificationDocumentsFlow: false,
			isApprover: false,
			phoneNumber: '',
		},
		...(libresignState?.settings || {}),
	},
}
