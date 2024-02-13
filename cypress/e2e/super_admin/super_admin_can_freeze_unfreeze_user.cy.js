// NO PERMANENT CHANGE TO DATABASE

const base_url = Cypress.config('baseUrl')

// temporarily disable uncaught exception handling
Cypress.on('uncaught:exception', (err, runnable) => {
    return false
})

describe('super admin can freeze and unfreeze user', function () {
    it('super admin path', function () {
        cy.visit(base_url)
        cy.get('#login').click()

        cy.log("fill in the login form")
        cy.get('#username').type('admin', {force: true})
        cy.get('#password').type('password', {force: true})
        cy.get('button[type=submit]').click()

        // navigate to admin page
        cy.get('#admin_link').click()

        // navigate to users page
        cy.get('#users_link').click()

        cy.get('#member_9').click()

        // open on freeze options
        cy.get('#freeze').click()
        cy.log('click on freeze immediately option')
        cy.get('#open_freeze_member_confirmation_modal').click()

        cy.log('click on modal confirmation button')
        cy.get('#freeze_member_confirmation_modal_confirm').click()

        cy.log('search for "gelé" text')
        cy.contains('gelé')

        cy.log('open on freeze options')
        cy.get('#freeze').click()

        cy.log('click on unfreeze button')
        cy.get('#open_unfreeze_member_confirmation_modal').click()

        cy.log('click on modal confirmation button')
        cy.get('#unfreeze_member_confirmation_modal_confirm').click()

        cy.log('the text "gelé" should not be on the page')
        cy.contains(/(?<!dé)gelé/).should('not.exist')
    })

})
