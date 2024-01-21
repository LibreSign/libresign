import { loadState } from '@nextcloud/initial-state'
import { defaults } from 'lodash-es'

const libresignState = loadState('libresign', 'config', {})

export default {
	namespaced: true,

	state: defaults({}, libresignState?.settings || {}, {
		hasSignatureFile: false,
		identificationDocumentsFlow: false,
		isApprover: false,
		phoneNumber: '',
	}),
}
