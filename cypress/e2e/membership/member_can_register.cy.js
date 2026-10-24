// MODIFIES DATABASE: creates a new registration for member 50
//
// Relies on the seeded fixtures (FIXTURES_SEED, see .env.test): every member
// has one registration, dated a fixed number of days before the day the
// fixtures were loaded. A member can register again when the current
// registration expires within 28 days, i.e. when it is at least ~337 days old.
//   - member 1 registered 279 days ago: too early to register again
//   - member 50 registered 356 days ago: can register again
// Reload the fixtures (make db-fixtures-load) before running this spec again,
// as member 50 is not eligible anymore once it has passed.

const TOO_EARLY_MEMBER = 1
const ELIGIBLE_MEMBER = 50

function openRegistrationSection(memberNumber) {
    cy.visit(`/member/${memberNumber}/show`)
    cy.url().should('include', '/member/')

    cy.get('#registration', { timeout: 10000 }).should('exist')
    // Direct child only, not the nested sub-collapsibles
    cy.get('#registration > .collapsible-header').click()
    cy.get('#registration > .collapsible-body').should('be.visible')
}

describe('admin can manage membership registrations', function () {

    beforeEach(function () {
        cy.login('admin', 'password')
    })

    it('member show page displays registration section', function () {
        openRegistrationSection(TOO_EARLY_MEMBER)

        // The fixtures create one registration per member
        cy.get('#registration > .collapsible-body li[id^="registration_"]').should('have.length', 1)

        // The "Ré-adhésion" sub-collapsible exists (the admin is not member 1)
        cy.get('#registration > .collapsible-body').within(() => {
            cy.get('.collapsible-header').should('contain', 'Ré-adhésion')
        })
    })

    it('tells that it is too early to register a member again', function () {
        openRegistrationSection(TOO_EARLY_MEMBER)

        cy.get('#registration .new_registration_form').closest('li').find('> .collapsible-header').click()
        cy.get('#registration .new_registration_form')
            .should('be.visible')
            .and('contain', 'trop tôt pour ré-adhérer')
        cy.get('#registration .new_registration_form form').should('not.exist')
    })

    it('admin can re-register a member whose registration is about to expire', function () {
        openRegistrationSection(ELIGIBLE_MEMBER)

        cy.get('#registration > .collapsible-body li[id^="registration_"]').should('have.length', 1)

        // Open the Ré-adhésion collapsible to reveal the form. The form is
        // there only when the member can register again: if this fails, the
        // fixtures are not the seeded ones, or were already used by this spec.
        cy.get('#registration .new_registration_form').closest('li').find('> .collapsible-header').click()
        cy.get('#registration .new_registration_form form', { timeout: 10000 }).should('be.visible')

        // Fill the amount field (required, must be > 0)
        cy.get('#registration .new_registration_form form input[id$="_amount"]').clear().type('15')

        // Select a payment mode (Espèce = cash)
        cy.get('#registration .new_registration_form form select[id$="_mode"]').select('1', { force: true })

        cy.get('#registration .new_registration_form form button[type="submit"]').click()

        // Redirected to the member page with the success flash
        cy.url({ timeout: 10000 }).should('include', `/member/${ELIGIBLE_MEMBER}/show`)
        cy.get('body').should('contain', 'Enregistrement effectu')

        // The registration is listed, and the new one is valid for a year:
        // the member cannot register again right away.
        cy.get('#registration > .collapsible-header').click()
        cy.get('#registration > .collapsible-body li[id^="registration_"]').should('have.length', 2)
        cy.get('#registration .new_registration_form').should('contain', 'trop tôt pour ré-adhérer')
    })

})
