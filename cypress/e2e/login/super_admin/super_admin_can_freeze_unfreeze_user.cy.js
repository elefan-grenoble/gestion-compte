// NO PERMANENT CHANGE TO DATABASE

// temporarily disable uncaught exception handling
Cypress.on('uncaught:exception', (err, runnable) => {
    return false
})

describe('super admin can freeze and unfreeze user', function () {
    it('super admin path', function () {

        cy.login('admin', 'password')

        // Navigate directly to a known non-frozen, non-withdrawn member (member_number=1)
        cy.visit('/member/1/show')

        // wait for the member show page to be fully loaded (freeze section requires ROLE_USER_MANAGER)
        cy.get('[data-cy=freeze]', { timeout: 15000 }).should('exist')

        // ---- FREEZE ----
        // Intercept the freeze POST so we can wait for the server response
        cy.intercept('POST', '**/freeze').as('freezePost')

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

        // Wait for the POST to complete and the redirect to load
        cy.wait('@freezePost').its('response.statusCode').should('be.oneOf', [200, 302, 303])

        // After the POST redirect, the page reloads. Wait for the freeze section to appear.
        cy.log('verify freeze succeeded: collapsible header should say "Dégeler"')
        cy.get('[data-cy=freeze] .collapsible-header', { timeout: 30000 }).should('contain', 'Dégeler')
        // the beneficiary card badge should contain "gelé"
        cy.get('.card .badge').should('contain', 'gelé')

        // ---- UNFREEZE ----
        // Intercept the unfreeze POST
        cy.intercept('POST', '**/unfreeze').as('unfreezePost')

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

        // Wait for the POST to complete and the redirect to load
        cy.wait('@unfreezePost').its('response.statusCode').should('be.oneOf', [200, 302, 303])

        // After the POST redirect, the page reloads.
        cy.log('verify unfreeze succeeded: header text should revert to "Geler"')
        cy.get('[data-cy=freeze] .collapsible-header', { timeout: 30000 }).should('contain', 'Geler')
        // the "gelé" badge should no longer exist
        cy.get('.card .badge').should('not.contain', 'gelé')
    })

})
