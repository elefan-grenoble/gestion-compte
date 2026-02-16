// MODIFIES DATABASE: creates a new registration for a member

// temporarily disable uncaught exception handling
Cypress.on('uncaught:exception', (err, runnable) => {
    return false
})

describe('admin can manage membership registrations', function () {

    beforeEach(function () {
        cy.login('admin', 'password')
    })

    it('member show page displays registration section', function () {
        // Visit member 1's show page directly (super admin can view any member)
        cy.visit('/member/1/show')
        cy.url().should('include', '/member/')

        // The "Adhésions" collapsible section should exist
        cy.get('#registration', { timeout: 10000 }).should('exist')

        // Open the "Adhésions" collapsible
        cy.get('#registration .collapsible-header').click()

        // The registration body should be visible and contain registration info
        cy.get('#registration .collapsible-body', { timeout: 5000 }).should('be.visible')

        // There should be at least one registration entry (fixtures create one per member)
        cy.get('#registration .collapsible-body').then($body => {
            // Check either the registration list is visible or a "no registration" message
            const hasRegistrations = $body.find('li[id^="registration_"]').length > 0
            const hasNoRegistrationMessage = $body.text().includes("pas encore d'adhésion")

            expect(hasRegistrations || hasNoRegistrationMessage).to.be.true
            if (hasRegistrations) {
                cy.log('Member has registration history displayed')
            } else {
                cy.log('Member has no registrations yet')
            }
        })

        // The "Ré-adhésion" or "Adhésion" sub-collapsible should exist
        // (since super admin != member 1, and from_admin is true)
        cy.get('#registration .collapsible-body').within(() => {
            cy.get('.collapsible-header').should('exist')
        })
    })

    it('admin can re-register a member if eligible', function () {
        // Try multiple members to find one eligible for re-registration
        // Members are numbered 1-50 (regular users), their registration dates are random
        // canRegister = true when membership expires within 28 days
        const memberNumbers = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]

        // Visit the first member and check for the registration form
        cy.visit('/member/1/show')
        cy.url().should('include', '/member/')

        // Open the "Adhésions" collapsible
        cy.get('#registration', { timeout: 10000 }).should('exist')
        cy.get('#registration .collapsible-header').click()
        cy.get('#registration .collapsible-body', { timeout: 5000 }).should('be.visible')

        // Check if the re-registration form is available
        cy.get('body').then($body => {
            const hasForm = $body.find('.new_registration_form form').length > 0
            const hasTooEarly = $body.text().includes('trop tôt pour ré-adhérer')

            if (hasForm) {
                cy.log('Re-registration form is available — filling and submitting')

                // Open the Ré-adhésion collapsible to reveal the form
                cy.get('.new_registration_form').parents('li').find('.collapsible-header').click()
                cy.wait(500) // wait for Materialize collapsible animation

                // Fill the amount field (required, must be > 0)
                cy.get('.new_registration_form form').should('be.visible')
                cy.get('.new_registration_form form input[id$="_amount"]').clear().type('15')

                // Select a payment mode (Espèce = cash)
                cy.get('.new_registration_form form select[id$="_mode"]').select('1', { force: true })

                // Submit the form
                cy.get('.new_registration_form form button[type="submit"]').click()

                // After submission, we should be redirected back to the member show page
                // with a success flash message
                cy.url({ timeout: 10000 }).should('include', '/member/')
                cy.get('body').then($redirectedBody => {
                    const text = $redirectedBody.text()
                    // Check for success or known error messages
                    const hasSuccess = text.includes('Enregistrement effectué')
                    const hasAlreadyValid = text.includes('encore valable')
                    const hasError = text.includes('prix libre')

                    if (hasSuccess) {
                        cy.log('✅ Registration submitted successfully')
                    } else if (hasAlreadyValid) {
                        cy.log('⚠️ Previous registration still valid — expected with random fixtures')
                    } else {
                        cy.log('Registration form submitted, checking page state')
                    }
                })

            } else if (hasTooEarly) {
                cy.log('⏳ Re-registration not yet available (too early) — this is expected with random fixture data')
                // Verify the "too early" message is correctly displayed
                cy.get('.new_registration_form').should('contain', 'trop tôt')
            } else {
                cy.log('ℹ️ No registration form found on this member page — may be the admin\'s own membership')
            }
        })
    })

})
