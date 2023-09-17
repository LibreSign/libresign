import { getBoolean } from './helperTypes.js'

describe('Test getBoolean function', () => {
	it('should import function', () => {
		expect(getBoolean).not.toBeUndefined()
	})
	it('should return false when pass undefined', () => {
		expect(getBoolean(undefined)).toEqual(false)
	})
	it('should return false when pass 0', () => {
		expect(getBoolean(0)).toEqual(false)
	})
})
