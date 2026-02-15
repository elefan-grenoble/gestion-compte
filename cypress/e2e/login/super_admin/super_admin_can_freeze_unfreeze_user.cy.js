// NO PERMANENT CHANGE TO DATABASE

import {login} from "../login_reusables.cytools";

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

        // select a member that is NOT already frozen (no .frozen class on the row)
        cy.get('[data-cy^=member_]').not('.frozen').first().click()

        // wait for the member show page to be fully loaded
        cy.get('[data-cy=freeze]').should('exist')

        // ---- FREEZE ----
        cy.log('open freeze collapsible')
        cy.get('[data-cy=freeze] .collapsible-header').click()

        cy.log('wait for the "freeze immediately" button to be visible')
        cy.get('[data-cy=open_freeze_member_confirmation_modal]').should('be.visible').click()

        cy.log('wait for modal to open, then confirm')
        cy.get('[data-cy=freeze_member_confirmation_modal_confirm]').should('be.visible').click()

        cy.log('verify freeze succeeded: badge "gelé" should appear in the beneficiary card')
        // wait for page reload after POST+redirect: the collapsible header text changes
        cy.get('[data-cy=freeze] .collapsible-header').should('contain', 'Dégeler')
        // the beneficiary card badge should contain "gelé"
        cy.get('.card .badge').should('contain', 'gelé')

        // ---- UNFREEZE ----
        cy.log('open freeze collapsible')
        cy.get('[data-cy=freeze] .collapsible-header').click()

        cy.log('wait for the "unfreeze immediately" button to be visible')
        cy.get('[data-cy=open_unfreeze_member_confirmation_modal]').should('be.visible').click()

        cy.log('wait for modal to open, then confirm')
        cy.get('[data-cy=unfreeze_member_confirmation_modal_confirm]').should('be.visible').click()

        cy.log('verify unfreeze succeeded: header text should revert to "Geler"')
        cy.get('[data-cy=freeze] .collapsible-header').should('contain', 'Geler')
        // the "gelé" badge should no longer exist
        cy.get('.card .badge').should('not.contain', 'gelé')
    })

})
