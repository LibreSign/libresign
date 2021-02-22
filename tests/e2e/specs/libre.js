// https://docs.cypress.io/api/introduction/api.html

describe('Create User Form tooltips', () => {
	it('Visits the Create User page', () => {
	  cy.visit('http://localhost/index.php/apps/libresign/sign/73ce128b-8e5c-4581-b192-f46cd71805b7');
	});
	it('Email tooltip - show when required', () => {
		cy.get(':nth-child(2) > .has-tooltip').focus()
		cy.get(':nth-child(2) > .has-tooltip').blur()
		cy.contains(/^Insira seu email aqui.$/)
	});
	it('Email tooltip - hidde on focus', () => {
		cy.get(':nth-child(2) > .has-tooltip').focus()
		cy.get(':nth-child(2) > .has-tooltip').should('not.have.class', 'v-tooltip-open')
	});
	it('Email tooltip - hide on fill', () => {
		cy.get(':nth-child(2) > .has-tooltip').type('teste@teste.com')
		cy.get(':nth-child(2) > .has-tooltip').blur()
		cy.get(':nth-child(2) > .has-tooltip').should('not.have.class', 'v-tooltip-open')
	});
	it('Email tooltip - reepty email input to hidde tooltip', () => {
		cy.get(':nth-child(2) > .has-tooltip').clear()
		cy.get(':nth-child(2) > .has-tooltip').blur()
		cy.get(':nth-child(2) > .has-tooltip').should('have.class', 'v-tooltip-open')
	});


	it('Password tooltip - require min length', () => {
		cy.get(':nth-child(3) > .has-tooltip').focus()
		cy.get(':nth-child(3) > .has-tooltip').blur()
		cy.contains(/^A senha deve ter no mínimo 8 caracteres$/)
	});
	it('Password tooltip - hide tooltip on focus', () => {
		cy.get(':nth-child(3) > .has-tooltip').focus()
		cy.get(':nth-child(3) > .has-tooltip').should('not.have.class', 'v-tooltip-open')
	})
	it('Password tooltip - hide on fill', () => {
		cy.get(':nth-child(3) > .has-tooltip').type('12345678')
		cy.get(':nth-child(3) > .has-tooltip').blur()
	});
	it('Password tooltip - reepty password to hidde tooltip', () => {
		cy.get(':nth-child(3) > .has-tooltip').clear()
		cy.get(':nth-child(3) > .has-tooltip').blur()
		cy.get(':nth-child(3) > .has-tooltip').should('have.class', 'v-tooltip-open')
	});


	it('Confirm password tooltip - passwords do not match', () => {
		cy.get(':nth-child(3) > .has-tooltip').type('12345678')
		cy.get(':nth-child(4) > .has-tooltip').type('123456789')
		cy.get(':nth-child(4) > .has-tooltip').blur()
		cy.get(':nth-child(4) > .has-tooltip').should('have.class', 'v-tooltip-open')
	});
	it('Confirm password tooltip - hidde on focus', () => {
		cy.get(':nth-child(4) > .has-tooltip').focus()
		cy.get(':nth-child(4) > .has-tooltip').should('not.have.class', 'v-tooltip-open')
	});
	it('Confirm password tooltip - passwords match', () => {
		cy.get(':nth-child(3) > .has-tooltip').clear()
		cy.get(':nth-child(4) > .has-tooltip').clear()
		cy.get(':nth-child(3) > .has-tooltip').type('123456789')
		cy.get(':nth-child(4) > .has-tooltip').type('123456789')
		cy.get(':nth-child(4) > .has-tooltip').blur()
		cy.get(':nth-child(4) > .has-tooltip').should('not.have.class', 'v-tooltip-open')
	})


	it('PFX tooltip - show on mouse hover', () => {
		cy.get('.group.has-tooltip').trigger('mouseover').trigger('mouseenter')
		cy.contains('Senha para confirmar assinatura no documento!')
		// cy.get('.group.has-tooltip > input')
	});
	it('PFX tooltip - hidde on leave mouse', () => {
		cy.get('.group.has-tooltip')
			.trigger('mouseleave')
			.should('not.have.class', 'v-tooltip-open')
	});

	it('Submit button - disabled when fields are empty', () => {
		cy.get(':nth-child(2) > .has-tooltip').clear()
		cy.get(':nth-child(3) > .has-tooltip').clear()
		cy.get(':nth-child(4) > .has-tooltip').clear()
		cy.get('.group.has-tooltip > input').clear()

		cy.log(cy.get('.btn').should('be.disabled'))
	});
	it('Submit button - enable on fill correct inputs', () => {
		cy.get(':nth-child(2) > .has-tooltip').type('test@test.com')
		cy.get(':nth-child(3) > .has-tooltip').type('12345678')
		cy.get(':nth-child(4) > .has-tooltip').type('12345678')
		cy.get('.group.has-tooltip > input').type('123')

		cy.log(cy.get('.btn').should('not.be.disabled'))
	});
	it('Submit button - disable when filling in wrongly(Email)', () => {
		cy.get(':nth-child(2) > .has-tooltip').clear()
		cy.get(':nth-child(3) > .has-tooltip').clear()
		cy.get(':nth-child(4) > .has-tooltip').clear()
		cy.get('.group.has-tooltip > input').clear()

		cy.get(':nth-child(2) > .has-tooltip').clear()
		cy.get(':nth-child(3) > .has-tooltip').type('12345678')
		cy.get(':nth-child(4) > .has-tooltip').type('12345678')
		cy.get('.group.has-tooltip > input').type('123')

		cy.log(cy.get('.btn').should('be.disabled'))
	})
	it('Submit button - disable when filling in wrongly(Password)', () => {
		cy.get(':nth-child(2) > .has-tooltip').clear()
		cy.get(':nth-child(3) > .has-tooltip').clear()
		cy.get(':nth-child(4) > .has-tooltip').clear()
		cy.get('.group.has-tooltip > input').clear()

		cy.get(':nth-child(2) > .has-tooltip').type('test@test.com')
		cy.get(':nth-child(3) > .has-tooltip').type('123')
		cy.get(':nth-child(4) > .has-tooltip').type('12345678')
		cy.get('.group.has-tooltip > input').type('123')

		cy.log(cy.get('.btn').should('be.disabled'))
	})
	it('Submit button - disable when filling in wrongly(Confirm Password)', () => {
		cy.get(':nth-child(2) > .has-tooltip').clear()
		cy.get(':nth-child(3) > .has-tooltip').clear()
		cy.get(':nth-child(4) > .has-tooltip').clear()
		cy.get('.group.has-tooltip > input').clear()

		cy.get(':nth-child(2) > .has-tooltip').type('test@test.com')
		cy.get(':nth-child(3) > .has-tooltip').type('12345678')
		cy.get(':nth-child(4) > .has-tooltip').clear()
		cy.get('.group.has-tooltip > input').type('123')

		cy.log(cy.get('.btn').should('be.disabled'))
	})
	it('Submit button - disable when filling in wrongly(PFX)', () => {
		cy.get(':nth-child(2) > .has-tooltip').clear()
		cy.get(':nth-child(3) > .has-tooltip').clear()
		cy.get(':nth-child(4) > .has-tooltip').clear()
		cy.get('.group.has-tooltip > input').clear()

		cy.get(':nth-child(2) > .has-tooltip').type('test@test.com')
		cy.get(':nth-child(3) > .has-tooltip').type('12345678')
		cy.get(':nth-child(4) > .has-tooltip').type('12345678')
		cy.get('.group.has-tooltip > input').clear()

		cy.log(cy.get('.btn').should('be.disabled'))
	})
  });
