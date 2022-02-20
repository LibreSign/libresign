import { getInitialState } from '../../services/InitialStateService'
import { defaults } from 'lodash-es'

const libresignState = getInitialState()

export default {
	namespaced: true,

	state: defaults({}, libresignState?.settings || {}, {
		hasSignatureFile: false,
		isApprover: false,
		phoneNumber: '',
		signMethod: 'password',
	}),
}
