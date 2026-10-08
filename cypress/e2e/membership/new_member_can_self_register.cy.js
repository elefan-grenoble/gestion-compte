// MODIFIES DATABASE: creates a membership (and its first registration).
//
// Self-registration of a new member: a registrar invites an e-mail address
// (user_quick_new), the invited person follows the link of the mail, fills in
// their details and the membership is created. Needs the mailcatcher of the
// Cypress job.
//
// Limit: the "welcome" mail that asks to confirm the account is only checked
// for existence; following its link would test FOSUser, not this application.

const INVITED_EMAIL = 'new.member@example.org'

describe('new member can self register', function () {

    it('registrar invites an address, the invited person completes the membership', function () {
        cy.mailClear()

        // --- registrar side
        cy.login('admin', 'password')
        cy.visit('/user/quick_new')
        cy.get('form input[type=email]').first().type(INVITED_EMAIL)
        cy.get('form input[id$="_amount"]').type('15')
        cy.get('form select[id$="_mode"]').select('1', { force: true })
        cy.get('form button[type=submit]').click()
        cy.get('body').should('contain', 'La nouvelle adhésion a bien été prise en compte')

        // --- invited person side, not logged in
        cy.clearCookies()
        cy.mailLastTo(INVITED_EMAIL, 'Bienvenue').then((html) => {
            const link = html.match(/\/member\/new\?code=[^"'<\s&]+/)
            expect(link, 'registration link in the mail').to.not.equal(null)

            cy.visit(link[0].replace(/&amp;/g, '&'))
        })

        cy.get('form input[id$="_firstname"]').type('Nadia')
        cy.get('form input[id$="_lastname"]').type('Newmember')
        cy.get('form input[id$="_street1"]').type('1 rue de la Coopérative')
        cy.get('form input[id$="_zipcode"]').type('38000')
        cy.get('form input[id$="_city"]').type('Grenoble')
        cy.get('form button[type=submit]').click()

        cy.url().should('not.include', '/member/new')
        cy.get('body').should('contain', 'Ton adhésion est maintenant finalisée')

        // The new member got the mail that asks to confirm the account
        cy.mailLastTo(INVITED_EMAIL, '').should('not.be.empty')
    })

    it('refuses a registration link that is not valid', function () {
        cy.visit('/member/new?code=not-a-valid-code')
        cy.get('body').should('contain', "Cette url n'est plus valide")
    })
})
