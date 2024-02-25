// NO PERMANENT CHANGE TO DATABASE

import {login} from "../login_reusables.cytools";

const base_url = Cypress.config('baseUrl')

// temporarily disable uncaught exception handling
Cypress.on('uncaught:exception', (err, runnable) => {
    return false
})

describe('super admin can freeze and unfreeze user', function () {
    it('super admin path', function () {

        login("admin", "password")

        // navigate to admin page
        cy.get('[data-cy=admin_link]').click()

        // navigate to users page
        cy.get('[data-cy=users_link]').click()

        cy.get('[data-cy=member_9]').click()

        // open on freeze options
        cy.get('[data-cy=freeze]').click()
        cy.log('click on freeze immediately option')
        cy.get('[data-cy=open_freeze_member_confirmation_modal]').click()

        cy.log('click on modal confirmation button')
        cy.get('[data-cy=freeze_member_confirmation_modal_confirm]').click()

        cy.log('search for "gelé" text')
        cy.contains('gelé')

        cy.log('open on freeze options')
        cy.get('[data-cy=freeze]').click()

        cy.log('click on unfreeze button')
        cy.get('[data-cy=open_unfreeze_member_confirmation_modal]').click()

        cy.log('click on modal confirmation button')
        cy.get('[data-cy=unfreeze_member_confirmation_modal_confirm]').click()

        cy.log('the text "gelé" should not be on the page')
        cy.contains(/(?<!dé)gelé/).should('not.exist')
    })

})
