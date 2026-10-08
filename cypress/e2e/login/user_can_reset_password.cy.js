// MODIFIES DATABASE: changes the password of "Liam Smith" (user_1).
// Reads the reset mail through the mailcatcher of the Cypress job.

const USERNAME = 'Liam Smith'
const EMAIL = 'LiamSmith@email.com'
const OLD_PASSWORD = 'password'
const NEW_PASSWORD = 'a-new-password-2024'

describe('user can reset a forgotten password', function () {

    it('resets the password from the mailed link', function () {
        cy.mailClear()

        cy.visit('/resetting/request')
        cy.get('form.fos_user_resetting_request #username').type(USERNAME)
        cy.get('form.fos_user_resetting_request input[type=submit]').click()

        cy.url().should('include', '/resetting/check-email')

        // The link of the mail carries the one-time token
        cy.mailLastTo(EMAIL, 'Réinitialisation').then((html) => {
            const link = html.match(/\/resetting\/reset\/[A-Za-z0-9_-]+/)
            expect(link, 'reset link in the mail').to.not.equal(null)

            cy.visit(link[0])
        })

        cy.get('form.fos_user_resetting_reset input[name="fos_user_resetting_form[plainPassword][first]"]').type(NEW_PASSWORD)
        cy.get('form.fos_user_resetting_reset input[name="fos_user_resetting_form[plainPassword][second]"]').type(NEW_PASSWORD)
        cy.get('form.fos_user_resetting_reset input[type=submit]').click()

        // FOSUser logs the user in after a successful reset
        cy.get('[data-cy=settings_link]', { timeout: 10000 }).should('exist')
    })

    it('accepts the new password and refuses the old one', function () {
        cy.login(USERNAME, NEW_PASSWORD)
        cy.get('[data-cy=settings_link]', { timeout: 10000 }).should('exist')

        cy.clearCookies()
        cy.login(USERNAME, OLD_PASSWORD)
        cy.get('[data-cy=settings_link]').should('not.exist')
        cy.url().should('include', '/login')
    })

    it('does not reveal anything for an unknown account, and sends no mail', function () {
        cy.mailClear()

        cy.visit('/resetting/request')
        cy.get('form.fos_user_resetting_request #username').type('nobody-knows-me')
        cy.get('form.fos_user_resetting_request input[type=submit]').click()

        cy.request(`${Cypress.env('MAILCATCHER_URL') || 'http://localhost:1080'}/messages`)
            .its('body').should('have.length', 0)
    })
})
