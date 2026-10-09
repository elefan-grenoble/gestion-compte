// MODIFIES DATABASE: "Liam Smith" books a shift, then an admin frees it.
//
// Self-contained: the shift is booked by the spec itself (same path as
// member_can_book_shift), so that the target does not depend on which shifts
// the fixtures happen to have booked. The member number of Liam is read from
// his home page rather than assumed.

// Shared by the two tests of the file (one browser, one module)
let memberNumber = null

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
    cy.get('body', { timeout: 10000 }).should('contain', 'Ce créneau a bien été réservé')
}

// Reload the member page, bounded, until the shift is listed; fail with the
// content of the section when it never is. (The 5 s result cache shared by
// all users that made this necessary is gone, SHIFT-QUOTA-CACHE; the bounded
// reload stays as a guard.)
function openMemberShifts(number, attemptsLeft) {
    cy.visit(`/member/${number}/show`)
    cy.url().should('include', `/member/${number}/show`)
    cy.get('#shifts').then(($section) => {
        if ($section.find('[id^="shift_"].card').length > 0) {
            // The section is collapsible and closed by default
            cy.get('#shifts > .collapsible-header').click()
            cy.get('#shifts > .collapsible-body').should('be.visible')
            return
        }
        if (attemptsLeft === 0) {
            throw new Error(`No shift card on /member/${number}/show. Shifts section: ` + $section.text().replace(/\s+/g, ' ').slice(0, 600))
        }
        cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting -- see above: bounded reload
        openMemberShifts(number, attemptsLeft - 1)
    })
}

// Same bounded reload as above, on the way out: reload until the list shrinks.
function expectShiftCount(number, expected, attemptsLeft) {
    cy.visit(`/member/${number}/show`)
    cy.get('#shifts').then(($section) => {
        const count = $section.find('[id^="shift_"].card').length
        if (count === expected) {
            return
        }
        if (attemptsLeft === 0) {
            throw new Error(`Expected ${expected} shift card(s) on /member/${number}/show, found ${count}`)
        }
        cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting -- bounded reload
        expectShiftCount(number, expected, attemptsLeft - 1)
    })
}

describe('admin can free a shift booked by a member', function () {

    // Two tests rather than one with a logout in between: Cypress clears the
    // session between tests, the database state is kept for the whole file.
    it('the member books a shift', function () {
        bookShiftAsLiam()

        cy.visit('/')
        cy.get('[data-cy=home_welcome_message]', { timeout: 10000 }).invoke('text').then((text) => {
            const found = text.match(/#(\d+)/)
            expect(found, 'member number on the home page').to.not.equal(null)
            memberNumber = found[1]
        })
    })

    it('the admin frees it and the member no longer holds it', function () {
        cy.login('admin', 'password')
        // Wait for the login to complete: visiting right away aborts it
        cy.get('[data-cy=settings_link]', { timeout: 10000 }).should('exist')

        openMemberShifts(memberNumber, 10)

        cy.get('[id^="shift_"].card').its('length').then((before) => {
            cy.get('[id^="shift_"].card a.modal-trigger[title="Libérer"]').first().click()
            cy.get('.modal.open', { timeout: 10000 }).should('be.visible')
            cy.get('.modal.open textarea, .modal.open input[id$="_reason"]').first().type('Cypress: member cannot come')
            cy.get('.modal.open').contains('button', 'Oui, libérer le créneau').click()

            cy.get('body').should('contain', 'Le créneau a bien été libéré')
            expectShiftCount(memberNumber, before - 1, 10)
        })
    })
})
