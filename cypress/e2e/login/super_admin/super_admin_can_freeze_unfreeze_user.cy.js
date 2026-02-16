// NO PERMANENT CHANGE TO DATABASE

// temporarily disable uncaught exception handling
Cypress.on('uncaught:exception', (err, runnable) => {
    return false
})

describe('super admin can freeze and unfreeze user', function () {
    it('super admin path', function () {

        cy.login('admin', 'password')

        // navigate to admin page
        cy.get('[data-cy=admin_link]').click()

        // navigate to users page
        cy.get('[data-cy=users_link]').click()

        // wait for the member list to load, then find a non-frozen, non-withdrawn member
        cy.get('[data-cy^=member_]', { timeout: 10000 }).should('have.length.greaterThan', 0)
        cy.get('[data-cy^=member_]').not('.frozen').not('.withdrawn').first().find('a').first().click()

        // wait for the member show page to be fully loaded (freeze section requires ROLE_USER_MANAGER)
        cy.get('[data-cy=freeze]', { timeout: 10000 }).should('exist')

        // ---- FREEZE ----
        cy.log('open freeze collapsible')
        cy.get('[data-cy=freeze] .collapsible-header').click()

        cy.log('wait for the "freeze immediately" button to be visible')
        cy.get('[data-cy=open_freeze_member_confirmation_modal]', { timeout: 5000 }).should('be.visible')
        // small wait for Materialize collapsible animation to finish
        cy.wait(500)
        cy.get('[data-cy=open_freeze_member_confirmation_modal]').click()

        cy.log('wait for modal to open, then confirm')
        cy.get('[data-cy=freeze_member_confirmation_modal_confirm]', { timeout: 5000 }).should('be.visible')
        // small wait for Materialize modal animation to finish
        cy.wait(500)
        cy.get('[data-cy=freeze_member_confirmation_modal_confirm]').click()

        // After the POST, the page does a full redirect back to the member show page.
        // Wait for the new page to fully load by checking the freeze section exists again.
        cy.log('verify freeze succeeded: collapsible header should say "Dégeler"')
        cy.get('[data-cy=freeze]', { timeout: 15000 }).should('exist')
        cy.get('[data-cy=freeze] .collapsible-header', { timeout: 10000 }).should('contain', 'Dégeler')
        // the beneficiary card badge should contain "gelé"
        cy.get('.card .badge').should('contain', 'gelé')

        // ---- UNFREEZE ----
        cy.log('open freeze collapsible')
        cy.get('[data-cy=freeze] .collapsible-header').click()

        cy.log('wait for the "unfreeze immediately" button to be visible')
        cy.get('[data-cy=open_unfreeze_member_confirmation_modal]', { timeout: 5000 }).should('be.visible')
        // small wait for Materialize collapsible animation to finish
        cy.wait(500)
        cy.get('[data-cy=open_unfreeze_member_confirmation_modal]').click()

        cy.log('wait for modal to open, then confirm')
        cy.get('[data-cy=unfreeze_member_confirmation_modal_confirm]', { timeout: 5000 }).should('be.visible')
        // small wait for Materialize modal animation to finish
        cy.wait(500)
        cy.get('[data-cy=unfreeze_member_confirmation_modal_confirm]').click()

        // After the POST, the page does a full redirect back to the member show page.
        cy.log('verify unfreeze succeeded: header text should revert to "Geler"')
        cy.get('[data-cy=freeze]', { timeout: 15000 }).should('exist')
        cy.get('[data-cy=freeze] .collapsible-header', { timeout: 10000 }).should('contain', 'Geler')
        // the "gelé" badge should no longer exist
        cy.get('.card .badge').should('not.contain', 'gelé')
    })

})
