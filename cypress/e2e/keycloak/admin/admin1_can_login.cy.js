// NO PERMANENT CHANGE TO DATABASE

import {login} from "../keycloak_reusables.cytools";

let keycloakUrl = Cypress.env('KEYCLOAK_URL')


// temporarily disable uncaught exception handling
Cypress.on('uncaught:exception', (err, runnable) => {
    return false
})

describe('admin1 can login', function () {
    it('admin story', function () {

        cy.visit("/")
        cy.get('#login').click()

        cy.visit("/")
        cy.get('#login').click()

        cy.origin(keycloakUrl, () => {
            cy.log("fill in the login form")
            cy.get('#username').type('admin1', {force: true})
            cy.get('#password').type('password', {force: true})

            // submit
            cy.get('#kc-login').click()

            cy.location().then((location) => {
                if (location !== null && location.origin === keycloakUrl) {
                    cy.get('#kc-login').click()
                } else {
                    cy.log("not asked for access to user data")
                }
            })
        })

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
