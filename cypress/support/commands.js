// Custom commands for gestion-compte E2E tests
// https://on.cypress.io/custom-commands

/**
 * Login via the standard FOSUser login form.
 * @param {string} username
 * @param {string} password
 */
Cypress.Commands.add('login', (username, password) => {
    cy.visit('')
    cy.get('[data-cy=login]').click()

    cy.log('fill in the login form')
    cy.get('[data-cy=username]').type(username, { force: true })
    cy.get('[data-cy=password]').type(password, { force: true })
    cy.get('button[type=submit]').click()
})

/**
 * Login via the Keycloak OIDC flow.
 * Uses Cypress.env('KEYCLOAK_URL') by default.
 * @param {string} username
 * @param {string} password
 */
Cypress.Commands.add('loginKeycloak', (username, password) => {
    const keycloakUrl = Cypress.env('KEYCLOAK_URL')

    cy.visit('/')
    cy.get('#login').click()

    cy.origin(keycloakUrl, { args: { username, password, keycloakUrl } }, ({ username, password, keycloakUrl }) => {
        cy.log('fill in the Keycloak login form')
        cy.get('#username').type(username, { force: true })
        cy.get('#password').type(password, { force: true })

        cy.get('#kc-login').click()

        cy.location().then((location) => {
            if (location !== null && location.origin === keycloakUrl) {
                cy.get('#kc-login').click()
            } else {
                cy.log('not asked for access to user data')
            }
        })
    })
})