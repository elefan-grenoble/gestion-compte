// NO PERMANENT CHANGE TO DATABASE

import {login} from "../keycloak_reusables.cytools";

let keycloakUrl = Cypress.env('KEYCLOAK_URL')


// temporarily disable uncaught exception handling
Cypress.on('uncaught:exception', (err, runnable) => {
    return false
})

describe('admin1 can login', function () {
    it('admin story', function () {

        login(keycloakUrl, 'admin1', 'password');

        cy.log('home page banner contains "admin"')
        cy.get('[data-cy=home_welcome_message]').contains('admin')

        cy.log('go to settings page')
        cy.get('[data-cy=settings_link]').click()

        cy.log('display informations')
        cy.get('[data-cy=open_my_informations]').click()

        cy.log('check if role_admin in the page')
        cy.get('[data-cy=user_roles_container]').contains('ROLE_ADMIN', {timeout: 2000})

    })
})
