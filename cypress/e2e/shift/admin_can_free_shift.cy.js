// MODIFIES DATABASE: "Liam Smith" books a shift, then an admin frees it.
//
// Self-contained: the shift is booked by the spec itself (same path as
// member_can_book_shift), so that the target does not depend on which shifts
// the fixtures happen to have booked. Liam is the main beneficiary of member 1.

const MEMBER = 1

function bookShiftAsLiam() {
    cy.login('Liam Smith', 'password')
    cy.intercept('POST', '**/shift/*/book').as('shiftBook')
    cy.visit('/booking/')
    cy.get('h4.header', { timeout: 10000 }).should('contain', 'Créneaux disponibles')

    // Second day of the list: 2 of the 4 places of the "Reception des
    // livraisons" shift are already taken by others, Liam can book one.
    cy.get('#weeks li[data-date]').eq(1).as('day')
    cy.get('@day').then(($day) => {
        if (!$day.hasClass('active')) {
            cy.wrap($day).find('.collapsible-header').click()
        }
    })
    cy.get('@day')
        .find('.shift-bucket a.modal-trigger[data-tooltip="Reception des livraisons"]', { timeout: 15000 })
        .should('have.length', 1)
        .scrollIntoView()
        .click()
    cy.get('#modal-bucket', { timeout: 10000 }).should('be.visible')
    cy.get('#modal-bucket .checkedFormation').first().check({ force: true })
    cy.get('#modal-bucket #confirmButton').should('be.visible').click()
    cy.wait('@shiftBook', { timeout: 15000 }).its('response.statusCode').should('eq', 200)
    cy.url({ timeout: 15000 }).should('not.include', '/booking')
    cy.get('body', { timeout: 10000 }).should('contain', 'réservé')
}

describe('admin can free a shift booked by a member', function () {

    // Two tests rather than one with a logout in between: Cypress clears the
    // session between tests, the database state is kept for the whole file.
    it('the member books a shift', function () {
        bookShiftAsLiam()
    })

    it('the admin frees it and the member no longer holds it', function () {
        cy.login('admin', 'password')
        // Wait for the login to complete: visiting right away aborts it
        cy.get('[data-cy=settings_link]', { timeout: 10000 }).should('exist')
        cy.visit(`/member/${MEMBER}/show`)
        cy.url().should('include', `/member/${MEMBER}/show`)

        // The shifts are in a collapsible section of the member page
        cy.get('body').should('contain', 'Cycle en cours')
        cy.get('[id^="shift_"].card').its('length').then((before) => {
            cy.get('[id^="shift_"].card a.modal-trigger[title="Libérer"]').first().click()
            cy.get('.modal.open', { timeout: 10000 }).should('be.visible')
            cy.get('.modal.open textarea, .modal.open input[id$="_reason"]').first().type('Cypress: member cannot come')
            cy.get('.modal.open').contains('button', 'Oui, libérer le créneau').click()

            cy.get('body').should('contain', 'Le créneau a bien été libéré')
            cy.get('[id^="shift_"].card').should('have.length', before - 1)
        })
    })
})
