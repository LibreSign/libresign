
describe('Calc', () => {
	it('1 + 2 = 3 when passed', () => {
		const soma = (a, b) => {
			return a + b
		}

		expect(soma(1, 2)).toBe(3)
	})
})
