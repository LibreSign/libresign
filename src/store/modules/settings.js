import { defaults } from 'lodash-es'

import { loadState } from '@nextcloud/initial-state'

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
