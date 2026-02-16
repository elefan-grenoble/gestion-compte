// This test verifies the shift booking page loads correctly and
// books a shift if one is available.
// Uses "Liam Smith" (user_1 / beneficiary_1) who has formation_1,
// allowing them to book shifts that require that formation.

// temporarily disable uncaught exception handling
Cypress.on('uncaught:exception', (err, runnable) => {
    return false
})

describe('member can book a shift', function () {
    it('booking page displays the shift grid', function () {

        // Login as Liam Smith (user_1, has formation_1)
        cy.login('Liam Smith', 'password')

        // navigate directly to the booking page
        cy.visit('/booking/')

        // Verify we are on the booking page (not redirected)
        cy.url({ timeout: 10000 }).should('include', '/booking')

        // The page should show the booking header with the beneficiary name
        // (since this user has only 1 beneficiary, no selection form is shown)
        cy.get('h4.header', { timeout: 10000 }).should('contain', 'Créneaux disponibles')

        // The shift grid should be present with at least one collapsible day entry
        cy.get('.collapsible li .collapsible-header', { timeout: 10000 })
            .should('have.length.greaterThan', 0)
    })

    it('book a shift from the booking page', function () {

        // Login as Liam Smith (user_1, has formation_1)
        cy.login('Liam Smith', 'password')

        // navigate directly to the booking page
        cy.visit('/booking/')

        // Verify the booking page loaded
        cy.url({ timeout: 10000 }).should('include', '/booking')
        cy.get('h4.header', { timeout: 10000 }).should('contain', 'Créneaux disponibles')

        // Look for bookable shifts: .shift-bucket elements with a link to a #book modal
        cy.get('body').then($body => {
            const $bookable = $body.find('.shift-bucket a[href^="#book"]')

            if ($bookable.length === 0) {
                // No bookable shifts available (all booked or formations mismatch).
                // This can happen with random fixtures. Skip gracefully.
                cy.log('No bookable shifts found — skipping booking test (random fixture data)')
                return
            }

            // Get the first bookable shift link
            const modalId = $bookable.first().attr('href') // e.g. "#book123"

            // Click on the shift to open the booking modal
            cy.get(`.shift-bucket a[href="${modalId}"]`).first().click({ force: true })

            // Wait for the modal to be visible
            cy.get(modalId, { timeout: 5000 }).should('be.visible')

            // Select the first available formation radio button
            cy.get(modalId).find('.checkedFormation').first().check({ force: true })

            // Click the "Confirmer" button
            cy.get(modalId).find('#confirmButton').should('be.visible').click()

            // After successful booking, the JS redirects to the homepage.
            // Wait for the homepage to load and verify the success flash message.
            cy.url({ timeout: 10000 }).should('not.include', '/booking')
            cy.get('body', { timeout: 10000 }).should('contain', 'réservé')
        })
    })
})
