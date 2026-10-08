// MODIFIES DATABASE: adds a beneficiary to member 3, then edits it.
//
// Relies on the seeded fixtures (FIXTURES_SEED): every membership has a single
// beneficiary and MAXIMUM_NB_OF_BENEFICIARIES_IN_MEMBERSHIP is 2.

const MEMBER = 3

describe('admin can manage the beneficiaries of a member', function () {

    beforeEach(function () {
        cy.login('admin', 'password')
    })

    it('adds a beneficiary to a member', function () {
        cy.visit(`/member/${MEMBER}/show`)

        const form = `form[action$="/member/${MEMBER}/newBeneficiary"]`
        cy.contains('.collapsible-header', 'Ajouter un bénéficiaire').click()
        cy.get(`${form} input[id$="_user_email"]`).should('be.visible').type('second.beneficiary@example.org')
        cy.get(`${form} input[id$="_firstname"]`).type('Sacha')
        cy.get(`${form} input[id$="_lastname"]`).type('Secondary')
        cy.get(`${form} input[id$="_street1"]`).type('2 rue de la Coopérative')
        cy.get(`${form} input[id$="_zipcode"]`).type('38000')
        cy.get(`${form} input[id$="_city"]`).type('Grenoble')
        cy.get(`${form} button[type=submit]`).click()

        cy.url().should('include', `/member/${MEMBER}/show`)
        cy.get('body').should('contain', 'Beneficiaire ajouté').and('contain', 'Sacha')
    })

    it('edits a beneficiary', function () {
        // Added by the previous test: the file shares one database state
        cy.visit(`/member/${MEMBER}/show`)
        cy.contains('[id^="beneficiary_"]', 'Sacha').find('form[action*="/beneficiary/"][action$="/edit"] button[type=submit]').click()

        cy.url().should('match', /\/beneficiary\/\d+\/edit/)
        cy.get('form input[id$="_phone"]').clear().type('0612345678')
        cy.get('form input[id$="_city"]').clear().type('Echirolles')
        cy.get('form input[type=submit][value="Modifier"]').click()

        cy.get('body').should('contain', 'Mise à jour effectuée')

        // The change is persisted
        cy.contains('[id^="beneficiary_"]', 'Sacha').find('form[action*="/beneficiary/"][action$="/edit"] button[type=submit]').click()
        cy.get('form input[id$="_phone"]').should('have.value', '0612345678')
        cy.get('form input[id$="_city"]').should('have.value', 'Echirolles')
    })

    it('refuses an invalid beneficiary', function () {
        cy.visit(`/member/${MEMBER}/show`)
        cy.contains('[id^="beneficiary_"]', 'Sacha').find('form[action*="/beneficiary/"][action$="/edit"] button[type=submit]').click()

        cy.get('form input[id$="_firstname"]').clear()
        cy.get('form input[type=submit][value="Modifier"]').click()

        cy.url().should('match', /\/beneficiary\/\d+\/edit/)
        cy.get('body').should('not.contain', 'Mise à jour effectuée')
    })
})
