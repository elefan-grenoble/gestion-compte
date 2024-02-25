// NO PERMANENT CHANGE TO DATABASE

// temporarily disable uncaught exception handling
import {login} from "../login_reusables.cytools";

Cypress.on('uncaught:exception', (err, runnable) => {
    return false
})

describe('super admin can login', function () {
    it('super admin path', function () {

        login("admin", "password")

        cy.log('go to settings page')
        cy.get('[data-cy=settings_link]').click()

        cy.log('display informations')
        cy.get('[data-cy=open_my_informations]').click()

        cy.log('check if role_admin in the page')
        cy.get('[data-cy=user_roles_container]').contains('ROLE_SUPER_ADMIN', {timeout: 2000})

    })

})
