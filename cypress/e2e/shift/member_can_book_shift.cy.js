// This test verifies the shift booking page loads correctly and books a shift.
// MODIFIES DATABASE: books a shift for "Liam Smith".
//
// Relies on the seeded fixtures (FIXTURES_SEED, see .env.test).
// "Liam Smith" (user_1 / beneficiary_1) has formation_1 (Réception des
// livraisons) and no shift yet, so he is a beginner: he can only join a
// bucket where someone already booked.

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

        cy.login('Liam Smith', 'password')

        // Intercept the shift booking POST request
        cy.intercept('POST', '**/shift/*/book').as('shiftBook')

        cy.visit('/booking/')
        cy.get('h4.header', { timeout: 10000 }).should('contain', 'Créneaux disponibles')

        // The fixtures create one bucket per day from tomorrow on, so the
        // second day of the list is the shift created for "+2 days": job
        // "Reception des livraisons" (formation_1), 4 places, 2 of them already
        // booked by other members, not locked. Liam can book one of the 2 left.
        // (Picked by position, not by date, so that a run past midnight does
        // not shift the target.)
        cy.get('#weeks li[data-date]').eq(1).as('day')
        cy.get('@day').find('.collapsible-header').should('be.visible')
        cy.get('@day').then(($day) => {
            // The first days open by themselves; open this one otherwise
            if (!$day.hasClass('active')) {
                cy.wrap($day).find('.collapsible-header').click()
            }
        })

        cy.get('@day')
            .find('.shift-bucket a.modal-trigger[data-tooltip="Reception des livraisons"]', { timeout: 15000 })
            .should('have.length', 1)
            .scrollIntoView()
            .click()

        // The modal loads the bucket (XHR) and lists the free places
        cy.get('#modal-bucket', { timeout: 10000 }).should('be.visible')
        cy.get('#modal-bucket').should('contain', 'Nombre de places restantes : 2/4')
        cy.get('#modal-bucket .checkedFormation').should('have.length', 2)
        cy.get('#modal-bucket .checkedFormation').first().check({ force: true })

        cy.get('#modal-bucket #confirmButton').should('be.visible').click()

        // 200: the response body is the URL the page then redirects to
        cy.wait('@shiftBook', { timeout: 15000 }).its('response.statusCode').should('eq', 200)

        cy.url({ timeout: 15000 }).should('not.include', '/booking')
        cy.get('body', { timeout: 10000 }).should('contain', 'réservé')
    })
})
