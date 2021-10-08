import { getInitialState } from './InitialStateService'
import { loadState } from '@nextcloud/initial-state'

describe('InitialStateService', () => {
	beforeAll(() => {
		jest.mock().resetAllMocks()

		jest.mock('@nextcloud/initial-state')
		jest.spyOn(console, 'error').mockImplementation(() => {})
	})

	afterAll(() => {
		console.error.mockRestore()
	})

	afterEach(() => {
		console.error.mockClear()
	})

	test('Return error if not have the the initialState', () => {
		getInitialState()
		expect(console.error).toHaveBeenCalled()
	})

	test('Return data if have initialState', () => {
		loadState.mockReturnValue('{"settings":{"hasSignatureFile":true}}')

		const initialState = getInitialState()
		expect(loadState).toHaveBeenCalledTimes(1)
		expect(loadState).toHaveBeenCalledWith('libresign', 'config')

		expect(initialState).toStrictEqual({ settings: { hasSignatureFile: true } })
	})
})
