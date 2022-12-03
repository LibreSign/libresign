import { getInitialState } from '../../services/InitialStateService.js'
import { defaults } from 'lodash-es'

const libresignState = getInitialState()

export default {
	namespaced: true,

	state: defaults({}, libresignState?.settings || {}, {
		hasSignatureFile: false,
    identificationDocumentsFlow: false,
    certificateOk: false,
		isApprover: false,
		phoneNumber: '',
		signMethod: 'password',
	}),
}
